<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\MessageReaction;
use App\Entity\User;
use App\Service\ReactionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class ToggleReactionController
{
    #[Route('/api/chats/{chatId}/messages/{messageId}/reactions', name: 'message_reaction_toggle', methods: ['POST'])]
    public function __invoke(
        string $chatId,
        string $messageId,
        Request $request,
        EntityManagerInterface $em,
        UserInterface $me,
        HubInterface $hub,
    ): JsonResponse {
        /** @var User $me */
        $chat = $em->getRepository(Chat::class)->find($chatId);
        if (!$chat) {
            return new JsonResponse(['error' => 'chat not found'], 404);
        }

        $membership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $msg = $em->getRepository(Message::class)->find($messageId);
        if (!$msg || (string) $msg->getChat()->getId() !== $chatId) {
            return new JsonResponse(['error' => 'message not found'], 404);
        }
        if ($msg->getDeletedAt()) {
            return new JsonResponse(['error' => 'cannot react to deleted message'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $emoji = trim($data['emoji'] ?? '');
        if ($emoji === '') {
            return new JsonResponse(['error' => 'emoji is required'], 400);
        }

        $existing = $em->getRepository(MessageReaction::class)->findOneBy([
            'message' => $msg,
            'user' => $me,
            'emoji' => $emoji,
        ]);

        if ($existing) {
            $em->remove($existing);
        } else {
            $reaction = new MessageReaction();
            $reaction->setMessage($msg);
            $reaction->setUser($me);
            $reaction->setEmoji($emoji);
            $em->persist($reaction);
        }
        $em->flush();

        $reactions = ReactionHelper::buildReactions($em, $msg);

        $topic = sprintf('/chats/%s/messages', $chatId);
        $hub->publish(new Update($topic, json_encode([
            'type' => 'message.reaction',
            'data' => [
                'chat_id'    => $chatId,
                'message_id' => $messageId,
                'reactions'  => $reactions,
            ],
        ], JSON_UNESCAPED_SLASHES), true));

        return new JsonResponse(['reactions' => $reactions]);
    }
}
