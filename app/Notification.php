<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\Types\Self_;

class Notification extends Model
{
    //
    public $timestamps = false;
    protected $guarded = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    // protected $appends = ['linkcontent'];

    /**
     * static method to create note
     */
    public static function saveNote($data = [], $fcm = false)
    {
        $validate = Validator::make($data, [
            'type' => 'bail|required|string',
            'receipient_id' => 'bail|required|string|exists:profiles,profile_id',
            'link' => 'bail|required|string',
        ]);

        if ($validate->fails()) {
            //dd($validate->errors());
            return false;
        }
        $query_data = array_merge($data, [
            'initiator_id' => auth()->user()->profile->profile_id
        ]);

        $other_data = array_merge($data, [
            'initiator_id' => auth()->user()->profile->profile_id,
            'linkmodel' => "{$data['type']}{$data['link']}",
            'created_at' => time(),
            'updated_at' => time(),
            'deleted' => false
        ]);
        $save = self::updateOrCreate(
            $query_data,
            $other_data
        );
        if (!$save) {
            return false;
        }
        return true;
    }

    /**
     * static method to delete Note
     */
    public static function deleteNote($data = [])
    {
        $validate = Validator::make($data, [
            'type' => 'bail|required|string',
            'receipient_id' => 'bail|required|string|exists:profiles,profile_id',
            'link' => 'bail|required|string',
        ]);

        if ($validate->fails()) {
            //dd($validate->errors());
            return false;
        }
        return self::where(
            array_merge($data, [
                'initiator_id' => auth()->user()->profile->profile_id,
            ])
        )->update(['deleted' => true]);
    }

    /**
     * static method to handle metions
     */
    public static function makeMentions($data = [], $type = '', $link = '', $fcm = true)
    {
        if (!is_array($data) || count($data) < 1 || empty($type) || empty($link)) {
            return false;
        }
        foreach ($data as $username) {
            Notification::mention($username, $type, $link, $fcm);
        }
        return true;
    }

    /**
     * static method to create mention
     */
    public static function mention($username = '', $type = '', $link = '', $fcm = true)
    {
        $auth_user = auth()->user();
        if (empty($username) || empty($type) || is_null($auth_user) || empty($link)) {
            return false;
        }
        $mentioned_user = User::where([
            ['id', '!=', $auth_user->id],
            ['username', '=', $username],
            'deleted' => false,
            'suspended' => false,
            'approved' => true,
        ])->whereHas('profile', function (Builder $query) use ($auth_user) {
            $query->where(function (Builder $query) use ($auth_user) {
                $query->whereHas('profile_settings', function (Builder $query) use ($auth_user) {
                    $query->where('blocked_profiles', 'not like', "%{$auth_user->profile->profile_id}%");
                });
                $query->orDoesntHave('profile_settings');
            });
        })->with('profile')->first();
        if (!$mentioned_user) {
            return false;
        }

        return Notification::saveNote([
            'type' => $type,
            'receipient_id' => $mentioned_user->profile->profile_id,
            'link' => $link,
            'is_mention' => true,
            'mentioned_name' => $username,
        ], $fcm);
    }

    /**
     * Static method to send fcm notification
     */
    private static function sendFcm($recipient_profile, $note)
    {
        $auth_user = auth()->user();
        if (is_null($recipient_profile) || is_null($note)) {
            return false;
        }
        $note_arr = [
            "to" => $recipient_profile->user->device_token,
            'priority' => 'high',
            'content-available' => true,
        ];

        switch ($note->type) {
            case 'postlike':
                $post = Post::with(['profile.user'])->firstWhere('postid', $note->link);
                break;
            case 'postshare':
                $note->post = Post::with(['profile.user'])->firstWhere('postid', $note->link);
                break;
            case 'postcomment':
                $note->postcomment = PostComment::with(['owner_post.profile.user', 'profile.user'])->firstWhere('commentid', $note->link);
                break;
            case 'postcommentlike':
                $note->postcomment = PostComment::with(['owner_post.profile.user', 'profile.user'])->firstWhere('commentid', $note->link);
                break;
            case 'postcommentreply':
                $note->postcommentreply = PostCommentReply::with(['origin.profile.user', 'profile.user'])->firstWhere('replyid', $note->link);
                break;
            case 'postcommentreplylike':
                $note->postcommentreply = PostCommentReply::with(['origin.profile.user', 'profile.user'])->firstWhere('replyid', $note->link);
                break;
            default:
                # code...
                break;
        }
        /*FCMNotification::send([
            "to" => $recipient_profile->user->device_token,
            'priority' => 'high',
            'content-available' => true,
            'data' => [
                'nav_id' => 'PRIVATECHAT',
                'notification' => [
                    'identity' => "note{$note->id}",
                    'id' => $note->id,
                    'name' => 'PrivateChat',
                    'body' => $body_text,
                    'sender' => auth()->user(),
                    'note_id' => "{$new_chat->private_chatid}",
                ],
                'resdata' => [
                    'type' => 'SET_FCM_PRIVATECHAT',
                    'payload' => [$new_chat, $userprofile->load('user')],
                ],
            ],
        ]);*/
    }

    /**
     * belongs To relationship.to return initator profile
     */
    public function initiator_profile()
    {
        return $this->belongsTo(Profile::class, 'initiator_id', 'profile_id');
    }

    /**
     * belongs To relationship to return recepient profile
     */
    public function recipient_profile()
    {
        return $this->belongsTo(Profile::class, 'recipient_id', 'profile_id');
    }
}
