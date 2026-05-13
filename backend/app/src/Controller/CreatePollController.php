<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\Poll;
use App\Entity\User;
use App\Service\PollHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class CreatePollController
{
    #[Route('/api/chats/{chatId}/messages/poll', name: 'poll_create', methods: ['POST'])]
    public function __invoke(
        string $chatId,
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

        $membership = $em->getRepository(ChatMember::class)->findOneBy(['chat' => $chat, 'member' => $me]);
        if (!$membership) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $question = trim($data['question'] ?? '');
        $rawOptions = $data['options'] ?? [];
        $multipleAnswers = (bool) ($data['multiple_answers'] ?? false);
        $anonymous = (bool) ($data['anonymous'] ?? false);

        if ($question === '') {
            return new JsonResponse(['error' => 'question is required'], 400);
        }

        if (!is_array($rawOptions) || count($rawOptions) < 2) {
            return new JsonResponse(['error' => 'at least 2 options are required'], 400);
        }

        $options = array_values(array_filter(array_map('strval', $rawOptions), static fn($o) => trim($o) !== ''));
        if (count($options) < 2) {
            return new JsonResponse(['error' => 'at least 2 non-empty options are required'], 400);
        }
        if (count($options) > 10) {
            return new JsonResponse(['error' => 'maximum 10 options'], 400);
        }

        $msg = new Message();
        $msg->setChat($chat);
        $msg->setSender($me);
        $msg->setType('poll');
        $msg->setContent($question);
        $em->persist($msg);

        $poll = new Poll();
        $poll->setMessage($msg);
        $poll->setQuestion($question);
        $poll->setOptions($options);
        $poll->setMultipleAnswers($multipleAnswers);
        $poll->setAnonymous($anonymous);
        $em->persist($poll);

        $em->flush();

        $pollData = PollHelper::buildPollData($em, $poll, $me);

        $msgData = [
            'id'               => (string) $msg->getId(),
            'chat_id'          => (string) $chat->getId(),
            'type'             => 'poll',
            'sender'           => $me->getUsername(),
            'sender_avatar_url' => $me->getAvatarUrl(),
            'content'          => $question,
            'created_at'       => $msg->getCreatedAt()?->format(DATE_ATOM),
            'reply_to'         => null,
            'attachments'      => null,
            'attachment_url'   => null,
            'attachment_type'  => null,
            'attachment_name'  => null,
            'reactions'        => [],
            'poll'             => $pollData,
        ];

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());
        $hub->publish(new Update($topic, json_encode(['type' => 'message.created', 'data' => $msgData], JSON_UNESCAPED_SLASHES), true));

        return new JsonResponse($msgData, 201);
    }
}
