<?php

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Notifications\SupportTicketReplyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('login');
    RateLimiter::clear('password-reset');
    config(['auth.device_verification_enabled' => false]);
});

it('requires authentication for support ticket endpoints per Handoff §3', function (): void {
    $this->getJson('/api/v1/support/tickets')->assertStatus(401);
    $this->postJson('/api/v1/support/tickets', [])->assertStatus(401);
    $this->getJson('/api/v1/support/tickets/tkt_sample123')->assertStatus(401);
    $this->postJson('/api/v1/support/tickets/tkt_sample123/messages', [])->assertStatus(401);
});

it('lists customer tickets scoped strictly to the authenticated customer per Handoff §1 & §3.1', function (): void {
    $customer1 = Customer::factory()->create(['username' => 'amara_nwosu']);
    $customer2 = Customer::factory()->create(['username' => 'other_user']);

    // Create 2 tickets for customer 1
    $ticket1 = SupportTicket::create([
        'customer_id' => $customer1->id,
        'subject' => 'Wallet not credited after transfer',
        'category' => TicketCategory::BILLING,
        'status' => TicketStatus::IN_PROGRESS,
        'has_unread_reply' => true,
    ]);
    $ticket1->messages()->create([
        'author_type' => 'customer',
        'author_id' => $customer1->id,
        'author_name' => $customer1->username,
        'body' => 'I made a transfer of ₦5,000 to my Korak wallet.',
    ]);

    $ticket2 = SupportTicket::create([
        'customer_id' => $customer1->id,
        'subject' => 'VPN node disconnects',
        'category' => TicketCategory::VPN,
        'status' => TicketStatus::OPEN,
        'has_unread_reply' => false,
    ]);
    $ticket2->messages()->create([
        'author_type' => 'customer',
        'author_id' => $customer1->id,
        'author_name' => $customer1->username,
        'body' => 'My connection on the Lagos node disconnects frequently.',
    ]);

    // Create 1 ticket for customer 2
    $ticketOther = SupportTicket::create([
        'customer_id' => $customer2->id,
        'subject' => 'Airtime failed',
        'category' => TicketCategory::AIRTIME_DATA,
        'status' => TicketStatus::OPEN,
    ]);

    $response = $this->actingAs($customer1, 'customer')
        ->getJson('/api/v1/support/tickets');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'reference',
                    'subject',
                    'category',
                    'status',
                    'message_count',
                    'has_unread_reply',
                    'latest_message_preview',
                    'created_at',
                    'updated_at',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);

    expect($response->json('meta.total'))->toBe(2);

    $firstItem = $response->json('data.0');
    expect(str_starts_with($firstItem['id'], 'tkt_'))->toBeTrue()
        ->and(str_starts_with($firstItem['reference'], 'TKT-'))->toBeTrue();

    // Verify Customer 2 sees only their 1 ticket
    $response2 = $this->actingAs($customer2, 'customer')
        ->getJson('/api/v1/support/tickets');

    $response2->assertOk();
    expect($response2->json('meta.total'))->toBe(1);
});

it('filters tickets by status and category and searches by reference or subject per Handoff §3.1', function (): void {
    $customer = Customer::factory()->create();

    $t1 = SupportTicket::create([
        'customer_id' => $customer->id,
        'subject' => 'Billing inquiry on refund',
        'category' => TicketCategory::BILLING,
        'status' => TicketStatus::OPEN,
    ]);
    $t1->messages()->create(['author_type' => 'customer', 'author_name' => 'Me', 'body' => 'Inquiry...']);

    $t2 = SupportTicket::create([
        'customer_id' => $customer->id,
        'subject' => 'VPN server sluggish',
        'category' => TicketCategory::VPN,
        'status' => TicketStatus::IN_PROGRESS,
    ]);
    $t2->messages()->create(['author_type' => 'customer', 'author_name' => 'Me', 'body' => 'Sluggish...']);

    // Filter status=in_progress
    $resStatus = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/support/tickets?filter[status]=in_progress');
    $resStatus->assertOk();
    expect($resStatus->json('meta.total'))->toBe(1)
        ->and($resStatus->json('data.0.status'))->toBe('in_progress');

    // Filter category=billing
    $resCat = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/support/tickets?filter[category]=billing');
    $resCat->assertOk();
    expect($resCat->json('meta.total'))->toBe(1)
        ->and($resCat->json('data.0.category'))->toBe('billing');

    // Search "refund"
    $resSearch = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/support/tickets?search=refund');
    $resSearch->assertOk();
    expect($resSearch->json('meta.total'))->toBe(1)
        ->and($resSearch->json('data.0.subject'))->toBe('Billing inquiry on refund');
});

it('validates ticket creation request payload per Handoff §3.2', function (): void {
    $customer = Customer::factory()->create();

    // 1. Missing fields
    $resEmpty = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/support/tickets', []);

    $resEmpty->assertStatus(422)
        ->assertJsonValidationErrors(['subject', 'category', 'message']);

    // 2. Subject too short (< 5) and message too short (< 20)
    $resShort = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/support/tickets', [
            'subject' => 'Drop',
            'category' => 'vpn',
            'message' => 'Short message',
        ]);

    $resShort->assertStatus(422)
        ->assertJsonValidationErrors(['subject', 'message'])
        ->assertJsonFragment([
            'errors' => [
                'subject' => ['Subject must be at least 5 characters.'],
                'message' => ['Please describe your issue in at least 20 characters.'],
            ],
        ]);

    // 3. Invalid category
    $resCat = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/support/tickets', [
            'subject' => 'Valid Subject Text',
            'category' => 'invalid_category_name',
            'message' => 'This is a valid long description of the issue encountered.',
        ]);

    $resCat->assertStatus(422)
        ->assertJsonValidationErrors(['category']);
});

it('creates support ticket with initial message per Handoff §3.2', function (): void {
    $customer = Customer::factory()->create(['username' => 'test_author']);

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/support/tickets', [
            'subject' => 'VPN connection drops frequently',
            'category' => 'vpn',
            'message' => 'My connection on the Lagos node disconnects every 10 minutes. Please investigate.',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'reference',
                'subject',
                'category',
                'status',
                'message_count',
                'has_unread_reply',
                'latest_message_preview',
                'created_at',
                'updated_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'subject' => 'VPN connection drops frequently',
                'category' => 'vpn',
                'status' => 'open',
                'message_count' => 1,
                'has_unread_reply' => false,
            ],
        ]);

    $ticketId = $response->json('data.id');
    expect(str_starts_with($ticketId, 'tkt_'))->toBeTrue();

    // Assert database has ticket and message
    $ticket = SupportTicket::where('ulid', $ticketId)->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->customer_id)->toBe($customer->id)
        ->and($ticket->messages()->count())->toBe(1);

    $message = $ticket->messages()->first();
    expect($message->author_type)->toBe('customer')
        ->and($message->author_id)->toBe($customer->id)
        ->and($message->author_name)->toBe('test_author')
        ->and(str_starts_with($message->ulid, 'msg_'))->toBeTrue();
});

it('retrieves ticket details with thread and marks admin replies as read per Handoff §3.3', function (): void {
    $customer = Customer::factory()->create(['username' => 'amara_nwosu']);

    $ticket = SupportTicket::create([
        'customer_id' => $customer->id,
        'subject' => 'Wallet not credited after transfer',
        'category' => TicketCategory::BILLING,
        'status' => TicketStatus::IN_PROGRESS,
        'has_unread_reply' => true,
    ]);

    // Customer message
    $ticket->messages()->create([
        'author_type' => 'customer',
        'author_id' => $customer->id,
        'author_name' => 'Amara Nwosu',
        'body' => 'I made a transfer of ₦5,000 to my Korak wallet.',
        'created_at' => now()->subHour(),
    ]);

    // Admin message (unread)
    $adminMsg = $ticket->messages()->create([
        'author_type' => 'admin',
        'author_name' => 'Support Team',
        'body' => 'Thank you for reaching out. Could you please share your bank reference?',
        'read_at' => null,
        'created_at' => now()->subMinutes(30),
    ]);

    expect($ticket->has_unread_reply)->toBeTrue()
        ->and($adminMsg->read_at)->toBeNull();

    // Customer views ticket detail
    $response = $this->actingAs($customer, 'customer')
        ->getJson("/api/v1/support/tickets/{$ticket->ulid}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'reference',
                'subject',
                'category',
                'status',
                'message_count',
                'has_unread_reply',
                'created_at',
                'updated_at',
                'messages' => [
                    '*' => [
                        'id',
                        'ticket_id',
                        'author',
                        'author_name',
                        'body',
                        'created_at',
                    ],
                ],
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'id' => $ticket->ulid,
                'reference' => $ticket->reference,
                'has_unread_reply' => false,
                'message_count' => 2,
            ],
        ]);

    // Verify side-effect: unread admin message is marked as read and has_unread_reply is false
    expect($ticket->fresh()->has_unread_reply)->toBeFalse()
        ->and($adminMsg->fresh()->read_at)->not->toBeNull();
});

it('prevents customer from viewing tickets belonging to another customer per Handoff §1', function (): void {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    $ticket = SupportTicket::create([
        'customer_id' => $customer1->id,
        'subject' => 'Secret Customer 1 Issue',
        'category' => TicketCategory::ACCOUNT,
        'status' => TicketStatus::OPEN,
    ]);

    $response = $this->actingAs($customer2, 'customer')
        ->getJson("/api/v1/support/tickets/{$ticket->ulid}");

    $response->assertStatus(404)
        ->assertJson(['code' => 'ticket_not_found']);
});

it('allows customer to reply to an open or in_progress ticket per Handoff §3.4', function (): void {
    $customer = Customer::factory()->create(['username' => 'amara_nwosu']);

    $ticket = SupportTicket::create([
        'customer_id' => $customer->id,
        'subject' => 'Wallet transfer issue',
        'category' => TicketCategory::BILLING,
        'status' => TicketStatus::IN_PROGRESS,
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson("/api/v1/support/tickets/{$ticket->ulid}/messages", [
            'message' => 'Here is the transaction reference: TXN20261117KOK48219.',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'ticket_id',
                'author',
                'author_name',
                'body',
                'created_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'ticket_id' => $ticket->ulid,
                'author' => 'customer',
                'author_name' => 'amara_nwosu',
                'body' => 'Here is the transaction reference: TXN20261117KOK48219.',
            ],
        ]);

    $messageId = $response->json('data.id');
    expect(str_starts_with($messageId, 'msg_'))->toBeTrue()
        ->and($ticket->messages()->count())->toBe(1);
});

it('rejects customer replies on resolved or closed tickets per Handoff §3.4', function (): void {
    $customer = Customer::factory()->create();

    $resolvedTicket = SupportTicket::create([
        'customer_id' => $customer->id,
        'subject' => 'Resolved issue',
        'category' => TicketCategory::GENERAL,
        'status' => TicketStatus::RESOLVED,
    ]);

    $closedTicket = SupportTicket::create([
        'customer_id' => $customer->id,
        'subject' => 'Closed issue',
        'category' => TicketCategory::GENERAL,
        'status' => TicketStatus::CLOSED,
    ]);

    // Attempt reply to resolved ticket
    $resResolved = $this->actingAs($customer, 'customer')
        ->postJson("/api/v1/support/tickets/{$resolvedTicket->ulid}/messages", [
            'message' => 'Can I still reply to this resolved issue?',
        ]);

    $resResolved->assertStatus(422)
        ->assertJson([
            'code' => 'ticket_locked',
            'message' => 'This ticket is resolved and cannot receive further replies.',
        ]);

    // Attempt reply to closed ticket
    $resClosed = $this->actingAs($customer, 'customer')
        ->postJson("/api/v1/support/tickets/{$closedTicket->ulid}/messages", [
            'message' => 'Can I still reply to this closed issue?',
        ]);

    $resClosed->assertStatus(422)
        ->assertJson([
            'code' => 'ticket_locked',
            'message' => 'This ticket is closed and cannot receive further replies.',
        ]);
});

it('allows admin to manage tickets, update status, and reply with notification to customer per Handoff §4', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create(['email' => 'customer_notify@example.com']);

    $ticket = SupportTicket::create([
        'customer_id' => $customer->id,
        'subject' => 'VPN node issue',
        'category' => TicketCategory::VPN,
        'status' => TicketStatus::OPEN,
        'has_unread_reply' => false,
    ]);
    $ticket->messages()->create([
        'author_type' => 'customer',
        'author_id' => $customer->id,
        'author_name' => $customer->username,
        'body' => 'VPN is dropping often.',
    ]);

    // 1. Admin lists tickets
    $adminList = $this->getJson('/api/v1/admin/support/tickets');
    $adminList->assertOk();
    expect($adminList->json('meta.total'))->toBe(1);

    // 2. Admin updates status to in_progress
    $statusUpdate = $this->patchJson("/api/v1/admin/support/tickets/{$ticket->ulid}/status", [
        'status' => 'in_progress',
    ]);
    $statusUpdate->assertOk()
        ->assertJson([
            'data' => [
                'status' => 'in_progress',
            ],
        ]);
    expect($ticket->fresh()->status)->toBe(TicketStatus::IN_PROGRESS);

    // 3. Admin replies to ticket
    $adminReply = $this->postJson("/api/v1/admin/support/tickets/{$ticket->ulid}/messages", [
        'message' => 'We have restarted the Lagos VPN node. Please test now.',
    ]);

    $adminReply->assertStatus(201)
        ->assertJson([
            'data' => [
                'author' => 'admin',
                'author_name' => 'Support Team',
                'body' => 'We have restarted the Lagos VPN node. Please test now.',
            ],
        ]);

    // Verify ticket has_unread_reply is true
    expect($ticket->fresh()->has_unread_reply)->toBeTrue();

    // Verify notification sent to customer
    Notification::assertSentTo($customer, SupportTicketReplyNotification::class);
});
