<?php

namespace Database\Factories;

use App\Domain\Cnpj;
use App\Models\Client;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $cnpj = $this->validNumericCnpj();

        return [
            'office_id' => Office::factory(),
            'name' => fake()->company(),
            'root_cnpj' => substr($cnpj, 0, 8),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function forOffice(Office $office): static
    {
        return $this->state(fn () => ['office_id' => $office->id]);
    }

    public function withRoot(string $root8): static
    {
        return $this->state(fn () => ['root_cnpj' => strtoupper(substr($root8, 0, 8))]);
    }

    private function validNumericCnpj(): string
    {
        // Gera base e calcula DV
        $base = str_pad((string) random_int(1, 999999999999), 12, '0', STR_PAD_LEFT);
        for ($i = 0; $i < 100; $i++) {
            $candidate = $base;
            // força tentativa com DV calculado via Cnpj helper interno
            $d1 = self::digit($candidate, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
            $d2 = self::digit($candidate.$d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
            $full = $candidate.$d1.$d2;
            if (Cnpj::tryParse($full) !== null) {
                return $full;
            }
            $base = str_pad((string) random_int(1, 999999999999), 12, '0', STR_PAD_LEFT);
        }

        return '11222333000181'; // fallback conhecido válido
    }

    /**
     * @param  list<int>  $weights
     */
    private static function digit(string $base, array $weights): string
    {
        $sum = 0;
        for ($i = 0, $len = strlen($base); $i < $len; $i++) {
            $sum += (ord($base[$i]) - 48) * $weights[$i];
        }
        $mod = $sum % 11;

        return (string) ($mod < 2 ? 0 : 11 - $mod);
    }
}
