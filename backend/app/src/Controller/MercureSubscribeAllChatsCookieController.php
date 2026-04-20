<?php

namespace App\Controller;

use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Mercure\Authorization;

final class MercureSubscribeAllChatsCookieController
{
    #[Route('/api/chats/mercure-subscribe', name: 'chats_mercure_subscribe_cookie', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserInterface $me,
        Authorization $authorization,
    ): JsonResponse {
        /** @var User $me */

        $memberships = $em->getRepository(ChatMember::class)->findBy(['member' => $me]);

        $topics = [];
        foreach ($memberships as $cm) {
            $chat = $cm->getChat();
            if (!$chat) continue;

            // Мы шлём ВСЁ (message.created/edited/deleted + chat.read/chat.delivered) в один topic:
            $topics[] = sprintf('/chats/%s/messages', (string) $chat->getId());
        }

        // Установит cookie mercureAuthorization на подписку
        $authorization->setCookie($request, $topics);

        return new JsonResponse([
            'topics' => $topics,
            'count' => count($topics),
        ]);
    }
}
