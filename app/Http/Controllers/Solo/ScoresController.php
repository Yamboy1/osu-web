<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Solo;

use App\Exceptions\InvariantException;
use App\Http\Controllers\Controller as BaseController;
use App\Libraries\ClientCheck;
use App\Libraries\Multiplayer\Mod;
use App\Models\Beatmap;
use App\Models\Solo\Score;
use PDOException;

class ScoresController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store($beatmapId)
    {
        $beatmap = $this->getBeatmapOrFail($beatmapId);
        $user = auth()->user();
        $params = request()->all();

        ClientCheck::assert($user, $params);

        $params = get_params($params, null, ['ruleset_id:int']);

        try {
            $score = Score::create(array_merge($params, [
                'user_id' => $user->getKey(),
                'beatmap_id' => $beatmap->getKey(),
                'started_at' => now(),
            ]));
        } catch (PDOException $e) {
            // TODO: move this to be a validation inside Score model
            throw new InvariantException('failed updating score');
        }

        return json_item($score, 'Solo\Score');
    }

    public function update($beatmapId, $id)
    {
        $this->getBeatmapOrFail($beatmapId);

        $score = Score::findOrFail($id);

        $params = get_params(request()->all(), null, [
            'accuracy:float',
            'max_combo:int',
            'mods:array',
            'passed:bool',
            'rank:string',
            'statistics:array',
            'total_score:int',
        ]);
        $params['ended_at'] = now();
        $params['mods'] = Mod::parseInputArray($params['mods'] ?? [], $score->ruleset_id);

        $score->complete($params);

        return json_item($score, 'Solo\Score');
    }

    private function getBeatmapOrFail($beatmapId)
    {
        return Beatmap::scoreable()->findOrFail($beatmapId);
    }
}
