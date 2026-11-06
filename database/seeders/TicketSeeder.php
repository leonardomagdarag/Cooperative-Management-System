<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The member-support inbox.
 *
 * Four tickets cover the states the desk works through — an open security
 * concern, one being worked, one resolved, and one closed — across the
 * categories members actually raise. The support thread is written with the
 * member's message first and the officer's reply after it, so the conversation
 * reads in order.
 *
 * Each ticket is matched on its member and subject, and its messages and
 * attachments are created in the same pass, so a re-seed leaves the thread
 * alone rather than appending duplicates.
 */
class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $threads = [
            [
                'member' => 'USR000007',
                'assigned_to' => 'USR000004',
                'subject' => 'Loan balance after my last payment',
                'category' => 'loans',
                'priority' => 'high',
                'status' => 'in_progress',
                'days_ago' => 6,
                'messages' => [
                    ['by' => 'member', 'days_ago' => 6, 'body' => 'Good day. I paid ₱2,000 on my loan last week but the balance on my app still looks the same. Can you check if my payment was posted?'],
                    ['by' => 'officer', 'days_ago' => 5, 'body' => 'Good day, Mr. Ramos. Your payment was posted on the counter and I can see it here. The app balance refreshes on the next loan review. I will confirm the running balance by tomorrow.'],
                    ['by' => 'officer', 'days_ago' => 5, 'internal' => true, 'body' => 'Checked the ledger: payment posted under a counter transaction. No action needed, just a display delay.'],
                ],
            ],
            [
                'member' => 'USR000012',
                'assigned_to' => 'USR000004',
                'subject' => 'Deposit not reflected in my passbook',
                'category' => 'savings_deposits',
                'priority' => 'medium',
                'status' => 'resolved',
                'days_ago' => 20,
                'resolved_days_ago' => 18,
                'messages' => [
                    ['by' => 'member', 'days_ago' => 20, 'body' => 'I deposited ₱1,500 at the branch on Monday. Here is the slip. It is not yet stamped in my passbook.'],
                    ['by' => 'officer', 'days_ago' => 18, 'body' => 'Thank you for the slip. The deposit is already in the ledger — the passbook was simply not updated. Please bring it on your next visit and we will stamp it.'],
                ],
                'attachment' => ['message_index' => 0, 'file' => 'deposit-slip.png', 'name' => 'deposit-slip.png'],
            ],
            [
                'member' => 'USR000014',
                'assigned_to' => null,
                'subject' => 'Someone tried to log in to my account',
                'category' => 'account_security',
                'priority' => 'urgent',
                'status' => 'open',
                'days_ago' => 1,
                'messages' => [
                    ['by' => 'member', 'days_ago' => 1, 'body' => 'I received a verification code by email even though I was not signing in. Please check if someone is trying to enter my account.'],
                ],
            ],
            [
                'member' => 'USR000010',
                'assigned_to' => 'USR000004',
                'subject' => 'How do I change my withdrawal limit?',
                'category' => 'withdrawals',
                'priority' => 'low',
                'status' => 'closed',
                'days_ago' => 40,
                'resolved_days_ago' => 38,
                'closed_days_ago' => 36,
                'messages' => [
                    ['by' => 'member', 'days_ago' => 40, 'body' => 'Hello, is there a maximum amount I can withdraw over the counter in one day?'],
                    ['by' => 'officer', 'days_ago' => 38, 'body' => 'Over-the-counter withdrawals have a per-request minimum only; the limit is set by the board and posted at the branch. For a large withdrawal, let us know a day ahead so we can prepare the cash.'],
                    ['by' => 'member', 'days_ago' => 37, 'body' => 'Understood, thank you!'],
                ],
            ],
        ];

        $created = 0;

        foreach ($threads as $thread) {
            $member = User::query()->where('user_number', $thread['member'])->first();
            if (! $member) {
                continue;
            }

            $exists = Ticket::query()
                ->where('user_id', $member->id)
                ->where('subject', $thread['subject'])
                ->exists();

            if ($exists) {
                continue;
            }

            $assignee = $thread['assigned_to']
                ? User::query()->where('user_number', $thread['assigned_to'])->first()
                : null;

            $createdAt = now()->subDays($thread['days_ago'])->setTime(9, 30);
            $lastReplyAt = null;
            $messages = [];

            $ticket = new Ticket([
                'user_id' => $member->id,
                'assigned_to' => $assignee?->id,
                'subject' => $thread['subject'],
                'category' => $thread['category'],
                'priority' => $thread['priority'],
                'status' => $thread['status'],
            ]);
            $ticket->timestamps = false;
            $ticket->created_at = $createdAt;
            $ticket->updated_at = $createdAt;
            $ticket->save();

            foreach ($thread['messages'] as $definition) {
                $author = $definition['by'] === 'member' ? $member : $assignee;
                if (! $author) {
                    continue;
                }

                $messageAt = now()->subDays($definition['days_ago'])->setTime(10, 15);
                $lastReplyAt = $messageAt;

                $message = new TicketMessage([
                    'ticket_id' => $ticket->id,
                    'user_id' => $author->id,
                    'message' => $definition['body'],
                    'is_internal_note' => (bool) ($definition['internal'] ?? false),
                ]);
                $message->timestamps = false;
                $message->created_at = $messageAt;
                $message->updated_at = $messageAt;
                $message->save();

                $messages[] = $message;
            }

            if (isset($thread['attachment'], $messages[$thread['attachment']['message_index']])) {
                $this->attachFile($ticket, $messages[$thread['attachment']['message_index']], $thread['attachment']);
            }

            $ticket->forceFill([
                'last_reply_at' => $lastReplyAt,
                'resolved_at' => isset($thread['resolved_days_ago']) ? now()->subDays($thread['resolved_days_ago'])->setTime(11, 0) : null,
                'closed_at' => isset($thread['closed_days_ago']) ? now()->subDays($thread['closed_days_ago'])->setTime(16, 0) : null,
                'updated_at' => now()->subDays($thread['resolved_days_ago'] ?? $thread['days_ago'])->setTime(11, 0),
            ])->save();

            $created++;
        }

        $this->command?->info('Recorded '.$created.' support tickets.');
    }

    /**
     * @param  array{file: string, name: string}  $attachment
     */
    private function attachFile(Ticket $ticket, TicketMessage $message, array $attachment): void
    {
        $path = 'tickets/'.$ticket->ticket_number.'/'.$attachment['file'];

        // A tiny 1x1 placeholder stands in for the scanned slip.
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Storage::disk('public')->put($path, $bytes);

        TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'ticket_message_id' => $message->id,
            'file_path' => $path,
            'file_name' => $attachment['name'],
            'file_size' => strlen($bytes),
            'mime_type' => 'image/png',
        ]);
    }
}
