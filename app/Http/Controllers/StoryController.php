<?php

namespace App\Http\Controllers;

use App\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Intervention\Image;

class StoryController extends Controller
{

    protected $user;
    protected $profile;
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
    }

    /**
     * Display a listing of the resource.
     *@param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        switch ($request->category) {
            case 'personal':
                return $this->getFollowingProfileStories();
                break;
            case 'trends':
                return $this->getTrendingStories();
                break;
            default:
                return [null];
                break;
        }

    }

    /**
     * Function returns stories of profiles that user is following
     * @return \Illuminate\Http\Response
     */
    public function getFollowingProfileStories()
    {
        $userprofile = $this->profile;
        $following_profile_stories = $userprofile->followings_stories()->with('profile.user')
            ->where('expired', false)
            ->simplePaginate(50);

        if (count($following_profile_stories) < 1) {
            return response()->json([
                'errmsg' => 'No new stories yet',
                'status' => 404,
            ]);
        }

        return response()->json([
            'message' => 'new stories found',
            'followers_stories' => $following_profile_stories->items(),
            'nextpageurl' => $following_profile_stories->nextPageUrl(),
            'status' => 302,
        ]);

    }
    /**
     * return hottest and trending stories
     */
    public function getTrendingStories()
    {
        $userprofile = $this->profile;
        $trendingstories = Story::with('profile.user')
            ->where('expired', false)
            ->orderBy('num_views', 'desc')
            ->simplePaginate(50);
        if (count($trendingstories) < 1) {
            return response()->json([
                'errmsg' => 'No trend stories yet',
                'status' => 404,
            ]);
        }

        return response()->json([
            'message' => 'new trends stories found',
            'trendstories' => $trendingstories->items(),
            'nextpageurl' => $trendingstories->nextPageUrl(),
            'status' => 302,
        ]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $userprofile = $this->profile;
        $validate = Validator::make($request->all(), [
            /*'story_text' => 'bail|sometimes|required|string|between:3,125',
        'story_color' => 'bail|sometimes|required|string|between:3,10',
        'anonymous' => 'required|boolean',
        'story_image' => 'bail|sometimes|required|image|file'*/
        ]);

        if ($validate->fails()) {
            $errors = $validate->errors();
            $story_text_error = $errors->first('story_text');
            $story_color_error = $errors->first('story_color');
            $anonymous_err = $errors->first('anonymous');
            $story_image_err = $errors->first('story_image');

            return response()->json([
                'errors' => [
                    'story_text_err' => $story_text_error,
                    'story_color_err' => $story_color_error,
                    'anonymous_err' => $anonymous_err,
                    'story_image_err' => $story_image_err,
                ],
                'status' => 400,
            ]);
        }

        if ($request->filled('story_text') == false && $request->filled('story_image') == false) {
            return response()->json([
                'errmsg' => 'Story text/image not found',
                'status' => 400,
            ]);
        }

        $reqstory = $request->only('story_text', 'story_color', 'anonymous');
        $storyimage = $this->storeStoryImage();
        $reqstory['story_id'] = '';
        if (is_array($storyimage)) {
            $reqstory['story_image'] = json_encode($story_image);
        }
        //save the story to database
        $story = $userprofile->stories()->create($reqstory);
        if (!$story) {
            return response()->json([
                'errmsg' => 'Could not post story please try again',
                'status' => 500,
            ]);
        }
        return response()->json([
            'message' => 'story posted!',
            'story' => $story,
            'status' => 201,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $profile = $this->profile;
        $storyid = $request->storyid;
        if (is_null($storyid) || empty($storyid)) {
            return response()->json([
                'errmsg' => 'Missing values to continue',
                'status' => 400,
            ]);
        }
        $story = Story::with('profile.user')->firstWhere('storyid', $storyid);
        if (is_null($story)) {
            return response()->json([
                'errmsg' => 'Story not found',
                'status' => 404,
            ]);
        }
        return response()->json([
            'message' => 'story found',
            'story' => $story,
            'status' => 302,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Story  $story
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Story $story)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Story  $story
     * @return \Illuminate\Http\Response
     */
    public function destroy(Story $story)
    {
        //
    }

    /***
     * this function handling uploading of image
     * @return \Illuminate\Http\Response
     *
     */
    public function storeStoryImage()
    {
        $profileid = $this->profile->profileid;
        if (request()->hasFile('story_image') && request()->file('story_image')->isValid()) {
            $tem_path = $request->story_image->path();
            $extension = $request->story_image->extension();
            $storyimage = Image::make($path);
            $story_thumb_nailImage = Image::make($path);
            $storyimage_path = 'public/images/uploads/storyimages/' . $profileid;
            $story_thumb_image_path = 'public/images/uploads/storythumbnailimages/' . $profileid;

            if ($story_image->save($storyimage_path)) {
                //create thumbnail and save if original image was saved successfully
                $story_thumb_nailImage->blur(20)->resize(70, 70, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                //if thumbnail image wasnt successfully saved then delete original image
                if (!$story_thumb_nailImage->save($story_thumb_image_path)) {
                    $this->deleteFile($storyimage_path);
                    $storyimage->destroy();
                    $story_thumb_nailImage->destroy();
                    return 'upload failed';
                }
                //uploads succefully return image data
                $storyimage->destroy();
                $story_thumb_nailImage->destroy();
                return [
                    'message' => 'image stored',
                    'story_image_path' => $storyimage_path,
                    'story_thumbnail_image_path' => $story_thumb_image_path,
                ];
            } else {
                return 'upload failed';
            }

        }
        return 'no image found';
    }

    /**
     * function to delete file from storage
     */
    public function deleteFile($file)
    {
        if (Storage::exists($file)) {
            Storage::delete($file);
        }
    }
}
