<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Carbon\Carbon;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // generate mockup data
        DebitCard::factory()
            ->count(10)
            ->active()
            ->for($this->user)
            ->create();

        // fake request
        $response = $this->getJson('/api/debit-cards');

        // mathching response
        $response->assertOk()
                ->assertStatus(200) // this is get, so just to make sure it is get 200 not 201(insert)
                ->assertJsonCount(10)
                ->assertJsonStructure(['*' => ['id', 'number', 'type', 'expiration_date', 'is_active']]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // generate mockup data for another user
        $otherUser = User::factory()->create();
        DebitCard::factory()->for($otherUser)->count(3)->create();

        // fake request
        $response = $this->getJson('/api/debit-cards');

        // mathching response
        $response->assertOk()
                 ->assertStatus(200)
                 ->assertJsonCount(0);
    }

    public function testCustomerCanCreateADebitCard()
    {
        // generate mockup payload
        $payload = ['type' => 'demas'];

        // fake insert request
        $response = $this->postJson('/api/debit-cards', $payload);
        
        // mathching response
        $response->assertStatus(201)
                 ->assertCreated()
                 ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'demas',
        ]);    
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // generate mockup data
        $debitCard = DebitCard::factory()->for($this->user)->create();

        // fake request
        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");


        // mathching response
        $response->assertStatus(200)
                ->assertJson([
                    'id' => $debitCard->id
                ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // generate mockup data
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->for($otherUser)->create();

        // fake request
        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        // mathching response
        $response->assertForbidden(); // Forbidden
    }

    public function testCustomerCanActivateADebitCard()
    {
        // generate mockup data
        $debitCard = DebitCard::factory()->for($this->user)->create(['disabled_at' => Carbon::now()]);

        // fake request
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => true]);

        // mathching response
        $response->assertOk()
                 ->assertStatus(200) //just want to make sure
                 ->assertJsonPath('data.disabled_at', null);
        $this->assertDatabaseHas('debit_cards', ['id' => $debitCard->id, 'disabled_at' => null]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // generate mockup data
        $debitCard = DebitCard::factory()->for($this->user)->create(['disabled_at' => null]);

        // fake request
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => false]);

        // mathching response
        $response->assertStatus(200);
        $this->assertFalse($debitCard->fresh()->is_active);        
        $this->assertDatabaseHas('debit_cards', ['id' => $debitCard->id, 'disabled_at' => Carbon::now()]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // generate mockup data
        $debitCard = DebitCard::factory()->for($this->user)->create();

        // fake request
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => 'not-a-boolean']);

        // mathching response
        $response->assertStatus(422) // Unprocessable Entity
                ->assertJsonValidationErrors('is_active');
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // generate mockup data
        $debitCard = DebitCard::factory()->for($this->user)->create();

        // fake request
        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        // mathching response
        $response->assertNoContent(); // 204
        $this->assertSoftDeleted('debit_cards', [
            'id' => $debitCard->id
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // generate mockup data
        $debitCard = DebitCard::factory()
            ->active()
            ->for($this->user)
            ->has(DebitCardTransaction::factory()->count(5), 'debitCardTransactions')
            ->create();

        // fake request
        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        // mathching response
        $response->assertForbidden(); // Bad Request or 409 Conflict depending on implementation
    }
}
