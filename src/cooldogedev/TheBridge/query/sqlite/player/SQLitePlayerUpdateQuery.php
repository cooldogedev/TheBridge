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

namespace cooldogedev\TheBridge\query\sqlite\player;

use cooldogedev\libSQL\query\SQLiteQuery;
use SQLite3;

final class SQLitePlayerUpdateQuery extends SQLiteQuery
{
    protected string $data;

    public function __construct(protected string $xuid, array $data)
    {
        $this->data = json_encode($data);
    }

    public function onRun(SQLite3 $connection): void
    {
        $data = json_decode($this->getData(), true);

        $statement = $connection->prepare($this->getQuery());
        $statement->bindValue(":xuid", $this->xuid);
        $statement->bindValue(":wins", $data["wins"]);
        $statement->bindValue(":win_streak", $data["win_streak"]);
        $statement->bindValue(":losses", $data["losses"]);
        $statement->bindValue(":kills", $data["kills"]);
        $statement->bindValue(":deaths", $data["deaths"]);
        $statement->bindValue(":goals", $data["goals"]);

        $statement->execute()?->finalize();
        $statement->close();

        $this->setResult($connection->changes() > 0);
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getQuery(): string
    {
        return "UPDATE " . $this->getTable() . " SET wins = :wins, win_streak = :win_streak, losses = :losses, kills = :kills, deaths = :deaths, goals = :goals WHERE xuid = :xuid";
    }

    public function getXuid(): string
    {
        return $this->xuid;
    }
}
