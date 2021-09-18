<?php

namespace App\Http\Controllers;

use App\ComplaintSuggestReport;
use App\PageVisits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ComplaintSuggestReportController extends Controller
{

    protected $user;
    protected $profile;
    protected $user_blocked_profiles_id = [];

    /**
     *Instantiate a new controller instance.
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.verify');
        $this->middleware('app.verify');
        $this->user = auth()->user();
        if (!is_null($this->user)) {
            $this->profile = $this->user->profile;
        } else {
            return;
        }
        if (!is_null($this->profile->profile_settings)) {
            $this->user_blocked_profiles_id = $this->profile->profile_settings->blocked_profiles;
        }
        PageVisits::saveVisit('post');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\Response
     */
    public function store(Request $req)
    {
        $userprofile = $this->profile;
        $validate = Validator::make($req->all(), [
            'type' => 'bail|required|string|between:1,2',
            'msg' => 'bail|sometimes|required|between:1,250',
            'model_name' => 'bail|sometimes|required|between:1,20',
            'reported_profile_id' => 'bail|sometimes|required|exists:profiles,profile_id',
            'link' => 'bail|sometimes|required|string',
        ]);
        if ($validate->fails()) {
            return response()->json([
                'errmsg' => 'bad request',
                'status' => 400,
            ]);
        }
        if ($req->type == "rp" && $req->missing('reported_profile_id')) {
            return response()->json([
                'errmsg' => 'Missing values to continue ',
                'status' => 400,
            ]);
        } elseif ($req->type == "r" && ($req->missing('model_name') || $req->missing('link') ||
            $req->missing('reported_profile_id'))) {
            return response()->json([
                'errmsg' => 'Missing values to continue g',
                'status' => 400,
            ]);
        } elseif ($req->type == "s" && $req->missing('msg')) {
            return response()->json([
                'errmsg' => 'Missing values to continue sgut',
                'status' => 400,
            ]);
        } elseif (!in_array($req->type, ['r', 'rp', 's'])) {
            return response()->json([
                'errmsg' => 'Unknown request',
                'status' => 400,
            ]);
        }
        $data = $req->only('type', 'msg', 'model_name', 'reported_profile_id', 'link');

        if ($req->block = "ok" && !$req->missing('reported_profile_id')) {
            $blocked_profiles = $this->user_blocked_profiles_id;

            if (!in_array($req->reported_profile_id, $blocked_profiles) &&
                $req->reported_profile_id != $this->profile->profile_id) {
                array_push($blocked_profiles, $req->reported_profile_id);
            }
            $block = $this->profile->profile_settings()->updateOrCreate(
                ['profile_id' => $this->profile->profile_id],
                ['blocked_profiles' => json_encode($blocked_profiles)]
            );
            if (!$block) {
                return response()->json([
                    'errmsg' => 'request could not be completed',
                    'status' => 500,
                ]);
            }
        }

        $save = ComplaintSuggestReport::create(
            array_merge($data,
                [
                    'created_at' => time(),
                    'updated_at' => time(),
                    'profile_id' => $this->profile->profile_id,
                ]
            )
        );
        if (!$save) {
            return response()->json([
                'errmsg' => 'request could not be completed',
                'status' => 500,
            ]);
        }
        return response()->json([
            'message' => 'done',
            'status' => 200,
        ]);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\ComplaintSuggestReport  $complaintSuggestReport
     * @return \Illuminate\Http\Response
     */
    public function show(ComplaintSuggestReport $complaintSuggestReport)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ComplaintSuggestReport  $complaintSuggestReport
     * @return \Illuminate\Http\Response
     */
    public function edit(ComplaintSuggestReport $complaintSuggestReport)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ComplaintSuggestReport  $complaintSuggestReport
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ComplaintSuggestReport $complaintSuggestReport)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ComplaintSuggestReport  $complaintSuggestReport
     * @return \Illuminate\Http\Response
     */
    public function destroy(ComplaintSuggestReport $complaintSuggestReport)
    {
        //
    }
}
