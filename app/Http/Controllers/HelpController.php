<?php

namespace App\Http\Controllers;

use App\Support\HelpCenter;
use Illuminate\Http\Request;

/** Help: the parts of the user manual that apply to the signed-in user's role. */
class HelpController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view("help.index", [
            "sections" => HelpCenter::sectionsFor($user),
            "roleSummary" => HelpCenter::render("role-" . $user->role, $user),
        ]);
    }

    public function show(Request $request, string $topic)
    {
        $user = $request->user();
        abort_unless(HelpCenter::allows($user, $topic), 404);

        return view("help.show", [
            "sections" => HelpCenter::sectionsFor($user),
            "topic" => HelpCenter::topic($topic),
            "content" => HelpCenter::render($topic, $user),
        ]);
    }
}
