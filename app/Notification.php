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
        if ($fcm) {
            Notification::sendFcm($save);
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
    private static function sendFcm($note)
    {
        if (
            empty($note)  ||
            $note->receipient_profile->profileblockedu == true  ||
            empty($note->receipient_profile->user->device_token)
        ) {
            return false;
        }
        $device_token = $note->receipient_profile->user->device_token;
        $note->receipient_profile = null;
        $note_arr = [
            'identity' => "note{$note->id}",
            'id' => $note->id,
        ];

        switch ($note->type) {
            case 'postlike':
                $note_arr['post'] = Post::with(['profile.user'])->firstWhere('postid', $note->link);
                $note_arr['title'] = "{$note->initiator_profile->profile_name} like your post";
                $note_arr['body'] = "{$note_arr['post']->post_text}";
                $note_arr['name'] = "PostLike";
                break;
            case 'postshare':
                $note_arr['post'] = Post::with(['profile.user'])->firstWhere('postid', $note->link);
                $note_arr['title'] = "{$note->initiator_profile->profile_name} like your post";
                $note_arr['name'] = "PostShare";
                break;
            case 'postcomment':
                $note_arr['postcomment'] = PostComment::with(['owner_post.profile.user'])->firstWhere('commentid', $note->link);
                $note_arr['title'] = "{$note->initiator_profile->profile_name} like your post";
                $note_arr['body'] = "{$note_arr['postcomment']->comment_text}";
                $note_arr['name'] = "PostComment";
                break;
            case 'postcommentlike':
                $note_arr['postcomment'] = PostComment::with(['owner_post.profile.user'])->firstWhere('commentid', $note->link);
                $note_arr['title'] = "{$note->initiator_profile->profile_name} like your comment";
                $note_arr['body'] = "{$note_arr['postcomment']->comment_text}";
                $note_arr['name'] = "PostCommentLike";
                break;
            case 'postcommentreply':
                $note_arr['postcommentreply'] = PostCommentReply::with(['origin.profile.user'])->firstWhere('replyid', $note->link);
                $note_arr['title'] = "{$note->initiator_profile->profile_name} replied to your comment";
                $note_arr['body'] = "{$note_arr['postcomentreply']->reply_text}";
                $note_arr['name'] = "PostCommentReply";
                break;
            case 'postcommentreplylike':
                $note_arr['postcommentreply'] = PostCommentReply::with(['origin.profile.user'])->firstWhere('replyid', $note->link);
                $note_arr['title'] = "{$note->initiator_profile->profile_name} liked your reply";
                $note_arr['body'] = "{$note_arr['postcomentreply']->reply_text}";
                $note_arr['name'] = "PostCommentReplyLike";
                break;
            case 'postmention':
                $note_arr['title'] = "{$note->initiator_profile->profile_name} liked your reply";
                $note_arr['post'] = Post::with(['profile.user'])->firstWhere('postid', $note->link);
                $note_arr['name'] = "Post";
                break;
            case 'postcommentmention ':
                $note_arr['title'] = "{$note->initiator_profile->profile_name} mentioned you in a comment";
                $note_arr['postcomment'] = PostComment::with(['owner_post.profile.user'])->firstWhere('commentid', $note->link);
                $note_arr['name'] = "PostComment";
                break;
            case 'postcommentreplymention':
                $note_arr['title'] = "{$note->initiator_profile->profile_name} mentioned you in a reply";
                $note_arr['postcommentreply'] = PostCommentReply::with(['origin.profile.user'])->firstWhere('replyid', $note->link);
                $note_arr['name'] = "PostCommentReply";
                break;
            case 'profilebiomention':
                $note_arr['title'] = "{$note->initiator_profile->profile_name} mentioned you in a bio";
                $note_arr['name'] = "Profile";
                break;
            default:
                # code...
                break;
        }

        // dd($note_arr);

        $t = FCMNotification::send([
            "to" => $device_token,
            'priority' => 'high',
            'content-available' => true,
            'data' => [
                'notification' => $note_arr,
            ],
        ]);

        dump([$t, $note_arr]);
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
    public function receipient_profile()
    {
        return $this->belongsTo(Profile::class, 'receipient_id', 'profile_id');
    }
}
