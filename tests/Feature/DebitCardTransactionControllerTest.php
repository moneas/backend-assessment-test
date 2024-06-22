<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // generate mockup data
        DebitCardTransaction::factory()->count(10)->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        // fake request
        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $this->debitCard->id);

        // mathching response
        $response->assertOk() //200
                ->assertJsonCount(10)                 
                ->assertJsonStructure(['*' => ['amount', 'currency_code']]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // generate mockup data
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        DebitCardTransaction::factory()->count(3)->create([
            'debit_card_id' => $otherDebitCard->id
        ]);

        // mathching response
        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $otherDebitCard->id);

        // mathching response
        $response->assertForbidden(); // 403
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // generate mockup param
        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ];

        // fake request
        $response = $this->postJson('/api/debit-card-transactions', $data);

        // mathching response
        $response->assertStatus(201)
                 ->assertJsonStructure([ 'amount', 'currency_code']);
        
        // matching DB
        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // generate mockup data for current user
        $otherUser = User::factory()->create();

        // generate mockup data for other user
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $data = [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 100,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ];

        // fake request
        $response = $this->postJson('/api/debit-card-transactions', $data);

        // mathching response
        $response->assertForbidden(); //403
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // generate mockup data
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        // fake request
        $response = $this->getJson('/api/debit-card-transactions/' . $transaction->id);

        // mathching response
        $response->assertOk() //200
                 ->assertJsonStructure(['amount', 'currency_code']);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // generate mockup data for current user
        $otherUser = User::factory()->create();

        // generate mockup data for other user
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        // generate mockup trx data for other user
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id
        ]);

        // fake request
        $response = $this->getJson('/api/debit-card-transactions/' . $transaction->id);

        // mathching response
        $response->assertForbidden(); //403
    }
}
