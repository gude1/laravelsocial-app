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
    public static function saveNote($data = [])
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
    public static function makeMentions($data = [], $type = '', $link = '')
    {
        if (!is_array($data) || count($data) < 1 || empty($type) || empty($link)) {
            return false;
        }
        foreach ($data as $username) {
            Notification::mention($username, $type, $link);
        }
        return true;
    }

    /**
     * static method to create mention
     */
    public static function mention($username = '', $type = '', $link = '')
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
            'is_mention' => true,
            'type' => $type,
            'receipient_id' => $mentioned_user->profile->profile_id,
            'link' => $link
        ]);
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
