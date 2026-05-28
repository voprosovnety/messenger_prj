<?php

namespace App\Controller;

use App\Entity\AvatarHistory;
use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
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
        $title     = isset($data['title'])      ? trim((string) $data['title']) : null;
        $avatarUrl = isset($data['avatar_url']) ? trim((string) $data['avatar_url']) : null;

        if ($title === null && $avatarUrl === null) {
            return new JsonResponse(['error' => 'title or avatar_url is required'], 400);
        }
        if ($title !== null && $title === '') {
            return new JsonResponse(['error' => 'title cannot be empty'], 400);
        }

        $members = $em->getRepository(ChatMember::class)->findBy(['chat' => $chat]);

        if ($title !== null) {
            $chat->setTitle($title);

            $sysMsg = new Message();
            $sysMsg->setChat($chat);
            $sysMsg->setType('system');
            $sysMsg->setContent($me->getUsername() . ' renamed the group to "' . $title . '"');
            $em->persist($sysMsg);

            $em->flush();

            $chatTopic = sprintf('/chats/%s/messages', $chatId);
            $sysMsgData = [
                'id'         => (string) $sysMsg->getId(),
                'chat_id'    => $chatId,
                'type'       => 'system',
                'sender'     => null,
                'content'    => $sysMsg->getContent(),
                'created_at' => $sysMsg->getCreatedAt()->format(DATE_ATOM),
            ];
            $hub->publish(new Update($chatTopic, json_encode(['type' => 'message.created', 'data' => $sysMsgData], JSON_UNESCAPED_SLASHES), true));

            $updatedPayload = json_encode([
                'type' => 'chat.updated',
                'data' => ['chat_id' => $chatId, 'title' => $title],
            ], JSON_UNESCAPED_SLASHES);
            foreach ($members as $member) {
                $hub->publish(new Update(sprintf('/users/%s', (string) $member->getMember()->getId()), $updatedPayload, true));
            }
        }

        if ($avatarUrl !== null) {
            $newUrl = $avatarUrl ?: null;
            if ($newUrl && $newUrl !== $chat->getAvatarUrl()) {
                $exists = $em->getRepository(AvatarHistory::class)->findOneBy(['chat' => $chat, 'avatarUrl' => $newUrl]);
                if (!$exists) {
                    $h = new AvatarHistory();
                    $h->setChat($chat);
                    $h->setAvatarUrl($newUrl);
                    $em->persist($h);
                }
            }
            $chat->setAvatarUrl($newUrl);
            $em->flush();

            $avatarPayload = json_encode([
                'type' => 'chat.updated',
                'data' => ['chat_id' => $chatId, 'avatar_url' => $newUrl],
            ], JSON_UNESCAPED_SLASHES);
            foreach ($members as $member) {
                $hub->publish(new Update(sprintf('/users/%s', (string) $member->getMember()->getId()), $avatarPayload, true));
            }
        }

        return new JsonResponse([
            'title'      => $chat->getTitle(),
            'avatar_url' => $chat->getAvatarUrl(),
        ]);
    }
}
