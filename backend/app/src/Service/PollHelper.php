<?php

namespace App\Service;

use App\Entity\Poll;
use App\Entity\PollVote;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PollHelper
{
    public static function buildPollData(EntityManagerInterface $em, Poll $poll, ?User $me): array
    {
        $votes = $em->getRepository(PollVote::class)->findBy(['poll' => $poll]);

        $options = $poll->getOptions();
        $counts  = array_fill(0, count($options), 0);
        $voters  = array_fill(0, count($options), []);

        foreach ($votes as $vote) {
            $idx = $vote->getOptionIndex();
            if (isset($counts[$idx])) {
                $counts[$idx]++;
                if (!$poll->isAnonymous()) {
                    $voters[$idx][] = $vote->getUser()?->getUsername();
                }
            }
        }

        $myVotes = [];
        if ($me) {
            foreach ($votes as $vote) {
                if ($vote->getUser()?->getId()?->equals($me->getId())) {
                    $myVotes[] = $vote->getOptionIndex();
                }
            }
        }

        $builtOptions = [];
        foreach ($options as $i => $text) {
            $builtOptions[] = [
                'id'     => $i,
                'text'   => $text,
                'votes'  => $counts[$i],
                'voters' => $voters[$i],
            ];
        }

        return [
            'id'               => (string) $poll->getId(),
            'question'         => $poll->getQuestion(),
            'options'          => $builtOptions,
            'multiple_answers' => $poll->isMultipleAnswers(),
            'anonymous'        => $poll->isAnonymous(),
            'allow_retraction' => $poll->isAllowRetraction(),
            'total_votes'      => count($votes),
            'my_votes'         => $myVotes,
        ];
    }
}
