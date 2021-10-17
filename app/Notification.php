<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
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
        $query_data = array_merge($data,[
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
    public static function deleteNote($data=[])
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
