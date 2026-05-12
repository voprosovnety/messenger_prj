<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class RenameChatController
{
    #[Route('/api/chats/{chatId}', name: 'chat_rename', methods: ['PATCH'])]
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

        if (!$chat->isGroup()) {
            return new JsonResponse(['error' => 'only group chats can be renamed'], 400);
        }

        $myMembership = $em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);

        if (!$myMembership || $myMembership->getRole() !== 'OWNER') {
            return new JsonResponse(['error' => 'only OWNER can rename the group'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return new JsonResponse(['error' => 'title cannot be empty'], 400);
        }

        $chat->setTitle($title);
        $em->flush();

        $members = $em->getRepository(ChatMember::class)->findBy(['chat' => $chat]);
        $payload = json_encode([
            'type' => 'chat.updated',
            'data' => ['chat_id' => $chatId, 'title' => $title],
        ], JSON_UNESCAPED_SLASHES);

        foreach ($members as $member) {
            $topic = sprintf('/users/%s', (string) $member->getMember()->getId());
            $hub->publish(new Update($topic, $payload, true));
        }

        return new JsonResponse(['title' => $title]);
    }
}
