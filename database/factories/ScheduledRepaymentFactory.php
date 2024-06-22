<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScheduledRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'loan_id' => fn () => Loan::factory()->create(),
            'amount' => empty(request('amount')) ? $this->faker->numberBetween(1000, 10000) : request('amount'),
            'outstanding_amount' => empty(request('amount')) ? $this->faker->numberBetween(1000, 10000) : request('amount'),
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => $this->faker->dateTimeThisYear(),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }
}
