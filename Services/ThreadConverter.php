<?php

namespace Modules\MFSEssentials\Services;

use App\Conversation;
use App\Customer;
use App\Policies\ThreadPolicy;
use App\Thread;
use App\User;

/**
 * Convert a single email in a conversation to an internal note, and back
 * (board card freescout-modules #261).
 *
 * Clients only ever see threads of type CUSTOMER and MESSAGE: the quoted
 * history in reply emails (app/Jobs/SendReplyToCustomer.php) and the End
 * User Portal (Conversation::getReplies()) both filter on those two types.
 * Changing the type to NOTE in place therefore hides the email from clients
 * everywhere, while its text, attachments, date and position stay as they
 * are.
 *
 * A note's author is rendered from created_by_user (thread.blade.php), so a
 * customer email cannot simply become a note: it would have no author and
 * break the print view. The converting agent becomes the author (which also
 * gives them core's Delete on the note); the original type and author are
 * kept in the thread's meta, shown in a banner, and restored on convert back.
 */
class ThreadConverter
{
    const META_KEY = 'mfse_converted';

    // threads.action_type is a tinyint; core uses 1-11, SpamFilter 101,
    // Workflows 201.
    const ACTION_TYPE_CONVERTED_TO_NOTE = 231;
    const ACTION_TYPE_CONVERTED_TO_EMAIL = 232;

    /**
     * Every agent with access to the conversation's mailbox may convert
     * (decision 1 on the card), same access rule core uses for threads.
     */
    public static function userCanConvert(User $user, Thread $thread)
    {
        $conversation = $thread->conversation;
        if (!$conversation) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        return $conversation->userHasAccessToMailbox($user->id)
            && (new ThreadPolicy())->checkIsOnlyAssigned($conversation, $user);
    }

    public static function canConvertToNote(Thread $thread)
    {
        return in_array($thread->type, [Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE])
            && $thread->state == Thread::STATE_PUBLISHED;
    }

    public static function isConverted(Thread $thread)
    {
        return $thread->type == Thread::TYPE_NOTE && is_array($thread->getMeta(self::META_KEY));
    }

    public static function convertToNote(Thread $thread, User $user)
    {
        $thread->setMeta(self::META_KEY, [
            'type'                   => (int) $thread->type,
            'created_by_user_id'     => $thread->created_by_user_id,
            'created_by_customer_id' => $thread->created_by_customer_id,
            'source_via'             => $thread->source_via,
            'by_user_id'             => $user->id,
            'at'                     => gmdate('Y-m-d H:i:s'),
        ]);
        $thread->type = Thread::TYPE_NOTE;
        $thread->created_by_user_id = $user->id;
        $thread->created_by_customer_id = null;
        $thread->source_via = Thread::PERSON_USER;
        $thread->save();

        self::addLineItem($thread->conversation, $user, self::ACTION_TYPE_CONVERTED_TO_NOTE);
    }

    public static function convertToEmail(Thread $thread, User $user)
    {
        $original = $thread->getMeta(self::META_KEY);

        $thread->type = $original['type'];
        $thread->created_by_user_id = $original['created_by_user_id'];
        $thread->created_by_customer_id = $original['created_by_customer_id'];
        $thread->source_via = $original['source_via'];

        $metas = $thread->getMetas();
        unset($metas[self::META_KEY]);
        $thread->setMetas($metas);
        $thread->save();

        self::addLineItem($thread->conversation, $user, self::ACTION_TYPE_CONVERTED_TO_EMAIL);
    }

    /**
     * Activity line in the conversation, like core's "marked as Closed".
     */
    protected static function addLineItem(Conversation $conversation, User $user, $actionType)
    {
        Thread::create($conversation, Thread::TYPE_LINEITEM, '', [
            'user_id'            => $conversation->user_id,
            'created_by_user_id' => $user->id,
            'action_type'        => $actionType,
            'source_via'         => Thread::PERSON_USER,
            'source_type'        => Thread::SOURCE_TYPE_WEB,
        ]);
    }

    /**
     * Data for the banner on a converted note: who converted it, when, and
     * who originally sent the email.
     */
    public static function bannerData(Thread $thread)
    {
        $original = $thread->getMeta(self::META_KEY);

        $by = User::find($original['by_user_id']);

        $from = '';
        if ($original['type'] == Thread::TYPE_CUSTOMER) {
            $customer = $original['created_by_customer_id'] ? Customer::find($original['created_by_customer_id']) : null;
            if ($customer) {
                $from = $customer->getFullName(true);
            }
            $email = $thread->from ?: ($customer ? $customer->getMainEmail() : '');
            if ($email && strpos($from, $email) === false) {
                $from = trim($from.' <'.$email.'>');
            }
        } else {
            $sender = $original['created_by_user_id'] ? User::find($original['created_by_user_id']) : null;
            $from = $sender ? $sender->getFullName() : '';
        }

        return [
            'is_reply' => $original['type'] == Thread::TYPE_MESSAGE,
            'by'       => $by ? $by->getFullName() : '',
            'at'       => \Carbon\Carbon::parse($original['at'], 'UTC'),
            'from'     => $from,
        ];
    }
}
