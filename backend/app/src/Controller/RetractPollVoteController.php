<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\Message;
use App\Entity\Poll;
use App\Entity\PollVote;
use App\Entity\User;
use App\Service\PollHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class RetractPollVoteController
{
    #[Route('/api/chats/{chatId}/messages/{messageId}/poll/vote', name: 'poll_vote_retract', methods: ['DELETE'])]
    public function __invoke(
        string $chatId,
        string $messageId,
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

        $msg = $em->getRepository(Message::class)->find($messageId);
        if (!$msg || (string) $msg->getChat()->getId() !== $chatId || $msg->getType() !== 'poll') {
            return new JsonResponse(['error' => 'poll message not found'], 404);
        }

        $poll = $em->getRepository(Poll::class)->findOneBy(['message' => $msg]);
        if (!$poll) {
            return new JsonResponse(['error' => 'poll not found'], 404);
        }

        if (!$poll->isAllowRetraction()) {
            return new JsonResponse(['error' => 'Vote retraction not allowed'], 403);
        }

        $votes = $em->getRepository(PollVote::class)->findBy(['poll' => $poll, 'user' => $me]);
        foreach ($votes as $vote) {
            $em->remove($vote);
        }
        $em->flush();

        $pollData = PollHelper::buildPollData($em, $poll, $me);

        $topic = sprintf('/chats/%s/messages', (string) $chat->getId());
        $payload = json_encode([
            'type' => 'poll.voted',
            'data' => [
                'message_id' => $messageId,
                'chat_id'    => $chatId,
                'poll'       => $pollData,
            ],
        ], JSON_UNESCAPED_SLASHES);
        $hub->publish(new Update($topic, $payload, true));

        return new JsonResponse(['poll' => $pollData]);
    }
}
