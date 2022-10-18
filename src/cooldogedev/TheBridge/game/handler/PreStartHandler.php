<?php

/**
 * Copyright (c) 2022 cooldogedev
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @auto-license
 */

declare(strict_types=1);

namespace cooldogedev\TheBridge\game\handler;

use cooldogedev\TheBridge\game\Game;
use cooldogedev\TheBridge\utility\message\KnownMessages;
use cooldogedev\TheBridge\utility\message\LanguageManager;
use cooldogedev\TheBridge\utility\message\TranslationKeys;

/**
 * This handler is responsible for handling the pre-start phases of the game.
 */
final class PreStartHandler extends IHandler
{
    protected const PHASE_IDLE = 0;
    protected const PHASE_COUNTDOWN = 1;

    protected int $timeLeft;
    protected int $phase = PreStartHandler::PHASE_IDLE;

    public function __construct(Game $game)
    {
        parent::__construct($game);

        $this->timeLeft = $this->getGame()->getData()->getCountdown();
    }

    public function getPhase(): int
    {
        return $this->phase;
    }

    public function setPhase(int $phase): void
    {
        $this->phase = $phase;
    }

    public function handleTicking(): void
    {
        $this->handleScoreboardUpdates();

        switch ($this->phase) {
            case PreStartHandler::PHASE_IDLE:
                // Start countdown if there are enough players.
                if ($this->game->getTeamManager()->canStart()) {
                    $this->game->broadcastMessage(LanguageManager::getMessage(KnownMessages::TOPIC_COUNTDOWN, KnownMessages::COUNTDOWN_START));
                    $this->setPhase(PreStartHandler::PHASE_COUNTDOWN);
                    return;
                }

                // Start the destruction of the game if empty.
                if (!$this->game->isLoading() && count($this->game->getPlayerManager()->getSessions()) < 1 && count($this->game->getQueueManager()->getQueues()) < 1) {
                    $this->game->startDestruction();
                }
                break;
            case PreStartHandler::PHASE_COUNTDOWN:
                // Tick the countdown.
                if ($this->timeLeft > 0) {
                    $this->timeLeft <= 5 && $this->game->broadcastMessage(LanguageManager::getMessage(KnownMessages::TOPIC_COUNTDOWN, KnownMessages::COUNTDOWN_DECREMENT), [
                        TranslationKeys::COUNTDOWN => $this->timeLeft,
                    ]);

                    $this->timeLeft--;
                    return;
                }

                // Revert to waiting state if there's no enough players.
                if (count($this->game->getPlayerManager()->getSessions()) < $this->game->getData()->getMinPlayersPerTeam()) {
                    $this->game->broadcastMessage(LanguageManager::getMessage(KnownMessages::TOPIC_COUNTDOWN, KnownMessages::COUNTDOWN_STOP));
                    $this->setPhase(PreStartHandler::PHASE_IDLE);
                    return;
                }

                $this->game->getQueueManager()->flush();

                $this->game->setHandler(new GraceHandler($this->game));
        }
    }

    public function handleScoreboardUpdates(): void
    {
        if ($this->timeLeft < 1) {
            return;
        }

        foreach ($this->game->getPlayerManager()->getSessions() as $session) {
            if (!$session->getPlayer()->isOnline()) {
                continue;
            }

            $translations = [
                TranslationKeys::MAP => $this->game->getData()->getName(),
                TranslationKeys::MODE => $this->game->getData()->getMode(),
                TranslationKeys::PLAYERS_COUNT => count($this->game->getPlayerManager()->getSessions()),
                TranslationKeys::COUNTDOWN => $this->timeLeft,
            ];

            $lines = array_map(fn($line) => $line !== "" ? LanguageManager::translate($line, $translations) : $line, $this->getScoreboardBody());

            $session->getScoreboardManager()->setLines($lines);
            $session->getScoreboardManager()->onUpdate();
        }
    }

    protected function getScoreboardBody(): array
    {
        $scoreboardData = LanguageManager::getArray(KnownMessages::TOPIC_SCOREBOARD, KnownMessages::SCOREBOARD_BODY);

        return $scoreboardData[$this->phase === PreStartHandler::PHASE_IDLE ? "idle" : "countdown"];
    }
}
