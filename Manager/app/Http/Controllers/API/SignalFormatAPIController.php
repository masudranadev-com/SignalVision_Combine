<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SignalFormat;
use App\Models\SignalFormatRequest;
use Illuminate\Support\Facades\DB;
use Validator;
use Storage;

class SignalFormatAPIController extends Controller
{
    //groups
    public function groups()
    {
        $groups = SignalFormat::select(
            DB::raw('COUNT(schedule_cryptos.provider_id) as total_users'),
            'signal_formats.logo',
            'signal_formats.format_name as name',
            'signal_formats.type',
            'signal_formats.features',
            'signal_formats.group_link'
        )
        ->leftJoin('schedule_cryptos', 'schedule_cryptos.provider_id', '=', 'signal_formats.id')
        ->where('signal_formats.showing_status', 'show')
        ->groupBy(
            'signal_formats.logo',
            'signal_formats.format_name',
            'signal_formats.type',
            'signal_formats.features',
            'signal_formats.group_link'
        )
        ->orderByDesc(DB::raw('COUNT(schedule_cryptos.provider_id)'))
        ->get();

        // Process results: decode features and apply logo URL
        $groups = $groups->map(function ($group) {
            $group->features = json_decode($group->features, true);
            $group->logo = Storage::url($group->logo); // Apply after query
            return $group;
        });

        return response()->json($groups);
    }

    // submitForm
    public function submitForm(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'group_name'     => 'required|string|max:255',
            'group_link'     => 'required|url',
            'sample_signal'  => 'required|string',
            'email'          => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $format = new SignalFormatRequest(); // Use the correct model
        $format->group_name = $req->group_name;
        $format->group_link = $req->group_link;
        $format->sample_signal = $req->sample_signal;
        $format->email = $req->email;
        $format->status = 'pending';
        $format->save();

        // ✅ Step 4: Return success response
        return response()->json([
            'status' => true,
            'msg' => 'Your signal request has been successfully submitted. Please wait a moment while we validate it.'
        ]);
    }
}
