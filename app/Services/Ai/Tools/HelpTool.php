<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Support\HelpCenter;

class HelpTool extends AiTool
{
    public function name(): string
    {
        return "help";
    }

    public function description(): string
    {
        return "How to use the system. Without topic: the help topics the user may read (key => title). With topic: the text of that topic "
            . "(only the parts for the user's role).";
    }

    public function ability(): ?string
    {
        return null;
    }

    protected function properties(): array
    {
        return ["topic" => ["type" => "string", "description" => "Topic key from the list"]];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $topic = self::text($input, "topic", 60);
        if ($topic === "") {
            $topics = [];
            foreach (HelpCenter::sectionsFor($user) as $section) {
                foreach ($section as $t) {
                    $topics[$t["key"]] = $t["title"];
                }
            }

            return ["topics" => $topics];
        }
        if (!HelpCenter::allows($user, $topic)) {
            return ["error" => "Unknown topic, or not available for this role."];
        }
        $text = trim(html_entity_decode(strip_tags(HelpCenter::render($topic, $user))));

        return ["topic" => $topic, "text" => mb_substr((string) preg_replace("/\n{3,}/", "\n\n", $text), 0, 12000)];
    }
}
