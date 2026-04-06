<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Mercure\Authorization;

final class MercureSubscribeCookieController
{
    #[Route('/api/chats/{chatId}/mercure-subscribe', name: 'chat_mercure_subscribe_cookie', methods: ['POST'])]
    public function __invoke(
        string $chatId,
        Request $request,
        EntityManagerInterface $em,
        UserInterface $me,
        Authorization $authorization,
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

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());

        // установит cookie mercureAuthorization
        $authorization->setCookie($request, [$topic]);

        return new JsonResponse(['topic' => $topic], 200);
    }
}
