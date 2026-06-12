<?php

final class ScheduleRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function update(int $scheduleId, string $day, string $hours): bool
    {
        $statement = $this->pdo->prepare("UPDATE horaire SET jour = ?, heures = ? WHERE id_horaire = ?");

        return $statement->execute([$day, $hours, $scheduleId]);
    }

    public function findAll(): array
    {
        $statement = $this->pdo->query("SELECT * FROM horaire ORDER BY id_horaire ASC");

        return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
