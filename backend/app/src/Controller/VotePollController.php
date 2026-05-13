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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class VotePollController
{
    #[Route('/api/chats/{chatId}/messages/{messageId}/poll/vote', name: 'poll_vote', methods: ['POST'])]
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

        $data = json_decode($request->getContent(), true);
        $requestedOptions = $data['options'] ?? [];

        if (!is_array($requestedOptions)) {
            return new JsonResponse(['error' => 'options must be an array'], 400);
        }

        $maxOptions = count($poll->getOptions());
        $requestedOptions = array_values(array_unique(array_filter(
            array_map('intval', $requestedOptions),
            static fn($i) => $i >= 0 && $i < $maxOptions
        )));

        if (!$poll->isMultipleAnswers() && count($requestedOptions) > 1) {
            $requestedOptions = [$requestedOptions[0]];
        }

        // Get current votes for this user
        $existingVotes = $em->getRepository(PollVote::class)->findBy(['poll' => $poll, 'user' => $me]);
        $existingIndices = array_map(static fn($v) => $v->getOptionIndex(), $existingVotes);

        // Toggle: remove votes for options already selected, add votes for new options
        // For single-answer: clear all then set new
        if (!$poll->isMultipleAnswers()) {
            foreach ($existingVotes as $vote) {
                $em->remove($vote);
            }
            if (count($requestedOptions) === 1 && !in_array($requestedOptions[0], $existingIndices, true)) {
                $vote = new PollVote();
                $vote->setPoll($poll);
                $vote->setUser($me);
                $vote->setOptionIndex($requestedOptions[0]);
                $em->persist($vote);
            }
        } else {
            // Multi-answer: toggle each option
            foreach ($requestedOptions as $idx) {
                $found = null;
                foreach ($existingVotes as $vote) {
                    if ($vote->getOptionIndex() === $idx) { $found = $vote; break; }
                }
                if ($found) {
                    $em->remove($found);
                } else {
                    $vote = new PollVote();
                    $vote->setPoll($poll);
                    $vote->setUser($me);
                    $vote->setOptionIndex($idx);
                    $em->persist($vote);
                }
            }
        }

        $em->flush();
        $em->clear();

        $poll = $em->getRepository(Poll::class)->findOneBy(['message' => $msg]);

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
