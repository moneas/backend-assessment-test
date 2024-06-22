<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create(),
            'terms' => $this->faker->randomNumber(1),
            'amount' => empty(request('amount')) ? $this->faker->numberBetween(1000, 10000) : request('amount'),
            'outstanding_amount' => empty(request('amount')) ? $this->faker->numberBetween(1000, 10000) : request('amount'),
            'currency_code' => Loan::CURRENCY_VND,
            'processed_at' => $this->faker->dateTimeThisYear(),
            'status' => Loan::STATUS_DUE,
        ];
    }
}
