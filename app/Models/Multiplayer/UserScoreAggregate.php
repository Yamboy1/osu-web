<?php

/**
 *    Copyright 2015-2018 ppy Pty. Ltd.
 *
 *    This file is part of osu!web. osu!web is distributed with the hope of
 *    attracting more community contributions to the core ecosystem of osu!.
 *
 *    osu!web is free software: you can redistribute it and/or modify
 *    it under the terms of the Affero GNU General Public License version 3
 *    as published by the Free Software Foundation.
 *
 *    osu!web is distributed WITHOUT ANY WARRANTY; without even the implied
 *    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *    See the GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with osu!web.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace App\Models\Multiplayer;

use App\Models\User;
use Illuminate\Support\Collection;

class UserScoreAggregate
{
    private $accuracy = 0;
    private $attempts = 0;
    private $completedCount = 0;
    private $pp = 0;
    private $roomId;
    private $stats = [];
    private $topScores = [];
    private $totalScore = 0;

    /** @var User */
    private $user;

    private $userStats = [];

    public function __construct(User $user, Room $room)
    {
        $this->user = $user;
        $this->roomId = $room->getKey();
    }

    public function addScores(Collection $scores)
    {
        foreach ($scores as $score) {
            $this->addScore($score);
        }
    }

    public function addScore(RoomScore $score)
    {
        if (!$score->isCompleted()) {
            return false; // throw instead?
        }

        $this->_addScore($score);

        return true;
    }

    public function _addScore(RoomScore $score)
    {
        $highestScore = static::read($score);

        if ($score->total_score > $highestScore->total_score) {
            $this->updateUserTotal($score, $highestScore);
            static::updatePlaylistItemUserHighScore($highestScore, $score);
        } else {
            $this->updateUserAttempts();
        }
    }

    public function recalculate()
    {
        $scores = $this->getScores();

        $this->removeRunningTotals();
        $this->addScores($scores);
    }

    public function getScores()
    {
        return RoomScore
            ::where('room_id', $this->roomId)
            ->where('user_id', $this->user->getKey())
            ->get();
    }

    public function removeRunningTotals()
    {
        RoomUserHighScore::where('room_id', $this->roomId)->where('user_id', $this->user->getKey())->delete();
        PlaylistItemUserHighScore::whereIn(
            'playlist_item_id',
            PlaylistItem::where('room_id', $this->roomId)->select('id')
        )->where('user_id', $this->user->getKey())->delete();
    }

    // lazy function for testing
    public static function read($score)
    {
        return PlaylistItemUserHighScore::firstOrNew([
            'playlist_item_id' => $score->playlist_item_id,
            'user_id' => $score->user_id,
        ]);
    }

    public function updateUserAttempts()
    {
        $total = $this->readUserTotal();
        $total->increment('attempts');

        return $total;
    }

    public function updateUserTotal(RoomScore $current, ?PlaylistItemUserHighScore $prev)
    {
        $total = $this->readUserTotal();
        $total->attempts++;

        if ($prev->exists) {
            $total->total_score -= $prev->total_score;
            $total->accuracy -= $prev->accuracy;
            $total->pp -= $prev->pp;
            $total->completed--;
        }

        $total->total_score += $current->total_score;
        $total->accuracy += $current->accuracy;
        $total->pp += $current->pp;
        $total->completed++;

        $total->save();

        return $total;
    }

    public function readUserTotal()
    {
        $total = RoomUserHighScore::firstOrNew(['room_id' => $this->roomId, 'user_id' => $this->user->getKey()]);
        foreach (['total_score', 'accuracy', 'pp', 'attempts', 'completed'] as $key) {
            // init if required
            $total->$key = $total->$key ?? 0;
        }

        return $total;
    }

    // lazy function for testing
    public static function updatePlaylistItemUserHighScore(PlaylistItemUserHighScore $highScore, RoomScore $score)
    {
        $highScore->total_score = $score->total_score;
        $highScore->accuracy = $score->accuracy;
        $highScore->pp = $score->pp;
        $highScore->score_id = $score->getKey();

        $highScore->save();
    }

    public function toArray() : ?array
    {
        $total = $this->readUserTotal();
        $completedCount = $total->completed;
        if ($completedCount === 0) {
            return null;
        }

        return [
            'accuracy' => $total['accuracy'] / $completedCount,
            'attempts' => $total['attempts'],
            'completed' => $completedCount,
            'pp' => $total['pp'] / $completedCount,
            'room_id' => $this->roomId,
            'total_score' => $total['total_score'],
            'user' => json_item($this->user, 'UserCompact', ['country']),
            'user_id' => $this->user->user_id,
        ];
    }


    // TODO: all needs fixing
    public function getAccuracyAverage() : float
    {
        return $this->accuracy / $this->completedCount;
    }

    public function getAttempts() : int
    {
        return $this->attempts;
    }

    public function getCompletedCount() : int
    {
        return $this->completedCount;
    }

    public function getPpAverage() : float
    {
        return $this->pp / $this->completedCount;
    }

    public function getTotalScore() : int
    {
        return $this->totalScore;
    }
}
