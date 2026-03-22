<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Model;

use Kanboard\Core\Base;

class PortfolioModel extends Base
{
    private const TABLE = 'portfolios';

    /**
     * @param array<string, mixed> $values
     *
     * @return int|false
     */
    public function create(array $values)
    {
        $name = $this->normalizeName($values['name'] ?? '');

        if (! $this->isValidName($name) || $this->getByName($name) !== null) {
            return false;
        }

        $timestamp = time();

        return $this->db->table(self::TABLE)->insert([
            'name' => $name,
            'description' => (string) ($values['description'] ?? ''),
            'owner_id' => (int) ($values['owner_id'] ?? 0),
            'is_active' => isset($values['is_active']) ? (int) $values['is_active'] : 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $portfolioId): ?array
    {
        $portfolio = $this->db->table(self::TABLE)->eq('id', $portfolioId)->findOne();

        return is_array($portfolio) ? $portfolio : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByName(string $name): ?array
    {
        $portfolio = $this->db->table(self::TABLE)->eq('name', $name)->findOne();

        return is_array($portfolio) ? $portfolio : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $portfolios = $this->db->table(self::TABLE)->asc('name')->findAll();

        return is_array($portfolios) ? $portfolios : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(int $portfolioId, array $values): bool
    {
        if ($this->getById($portfolioId) === null) {
            return false;
        }

        $updatedValues = [];

        if (array_key_exists('name', $values)) {
            $name = $this->normalizeName($values['name']);

            if (! $this->isValidName($name)) {
                return false;
            }

            $existing = $this->getByName($name);
            if ($existing !== null && (int) $existing['id'] !== $portfolioId) {
                return false;
            }

            $updatedValues['name'] = $name;
        }

        if (array_key_exists('description', $values)) {
            $updatedValues['description'] = (string) $values['description'];
        }

        if (array_key_exists('owner_id', $values)) {
            $updatedValues['owner_id'] = (int) $values['owner_id'];
        }

        if (array_key_exists('is_active', $values)) {
            $updatedValues['is_active'] = (int) $values['is_active'];
        }

        $updatedValues['updated_at'] = time();

        return (bool) $this->db->table(self::TABLE)->eq('id', $portfolioId)->update($updatedValues);
    }

    public function remove(int $portfolioId): bool
    {
        if ($this->getById($portfolioId) === null) {
            return false;
        }

        return (bool) $this->db->table(self::TABLE)->eq('id', $portfolioId)->remove();
    }

    /**
     * @param mixed $name
     */
    private function normalizeName($name): string
    {
        return trim((string) $name);
    }

    private function isValidName(string $name): bool
    {
        return $name !== '' && strlen($name) <= 255;
    }
}
