<?php

namespace App\GraphQL\Mutations;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

use Log;
use App\SongLyric;
use App\Song;
use App\Author;
use App\Songbook;
use function Safe\array_combine;

class UpdateSongLyric
{
    /**
     * Return a value for the field.
     *
     * @param  null  $rootValue Usually contains the result returned from the parent field. In this case, it is always `null`.
     * @param  mixed[]  $args The arguments that were passed into the field.
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context Arbitrary data that is shared between all fields of a single query.
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo Information about the query itself, such as the execution state, the field name, path to the field from the root, and more.
     * @return mixed
     */
    public function resolve($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $input = $args["input"];

        $song_lyric = SongLyric::find($input["id"]);

        // TODO as an event
        if ($input["name"] !== $song_lyric->name) {
            // to be domestic means to have a same name as the parent song
            // this invariant needs to be preserved in order to stay domestic
            if ($song_lyric->isDomestic()) {
                $song_lyric->song->update([
                    'name' => $input["name"]
                ]);
            }
        }

        $song_lyric->update($input);

        // todo if has key
        $this->handleArrangementSourceUpdate($input["arrangement_source"], $song_lyric);
        $this->handleSongGroup($input["song"], $song_lyric);

        // HANDLE AUTHORS
        $syncAuthors = [];

        if (isset($input["authors"]["create"])) {
            foreach ($input["authors"]["create"] as $author) {
                $a = Author::create([
                    'name' => $author['author_name']
                ]);

                $syncAuthors[$a->id] = [
                    'authorship_type' => $author['authorship_type']
                ];
            }
        }

        if (isset($input["authors"]["sync"])) {
            foreach ($input['authors']['sync'] as $author) {
                $syncAuthors[$author["author_id"]] = [
                    'authorship_type' => $author["authorship_type"]
                ];
            }
        }

        $song_lyric->authors_pivot()->sync($syncAuthors);
        $song_lyric->save();

        // HANDLE SONGBOOK RECORDS
        if (isset($input["songbook_records"]["sync"])) {
            $syncModels = [];
            foreach ($input["songbook_records"]["sync"] as $record) {
                $syncModels[$record["songbook_id"]] = [
                    'number' => $record["number"]
                ];
            }
            $song_lyric->songbook_records()->sync($syncModels);
        }
        // if (isset($input["songbook_records"]["create"])) {
        //     foreach ($input["songbook_records"]["create"] as $record) {
        //         // $songbook = Songbook::create(["name" => $record["songbook"]]);

        //         \Log::info($song_lyric);

        //         $song_lyric->songbook_records()->create([
        //             'name' => $record["songbook"]
        //         ], [
        //             'number' => $record["number"]
        //         ]);
        //     }
        // }

        $song_lyric->save();

        // reload from database
        return SongLyric::find($input["id"]);
    }

    private function handleSongGroup($song_input_data, $song_lyric)
    {
        // HANDLE ASSOCIATED SONG LYRICS
        if (isset($song_input_data)) {
            $sl_group = collect($song_input_data["song_lyrics"]);

            // 1. case: song was alone and now is added to a group
            if (!$song_lyric->hasSiblings() && $sl_group->count() > 1) {
                // add this song to that foreign group - aka change the song_id
                // todo: check that there are only two different song ids
                Log::info("situation 1");

                foreach ($sl_group as $sl_object) {
                    $new_sibling = SongLyric::find($sl_object["id"]);
                    $new_sibling->update([
                        'song_id' => $song_input_data["id"],
                        'type' => $sl_object["type"]
                    ]);
                }
            }
            // 2. case: song was in a group and now is alone
            elseif ($song_lyric->hasSiblings() && $sl_group->count() == 1) {
                // create new song and associate this song_lyric to that one
                Log::info("situation 2");

                $sl_object = $sl_group[0];

                $new_song = Song::create(["name" => $sl_object["name"]]);
                $former_sibling = SongLyric::find($sl_object["id"]);
                $former_sibling->update([
                    'song_id' => $new_song->id,
                    'type' => $sl_object["type"]
                ]);
            }
            // 3. case: no insertions/deletions, just update the types
            elseif ($song_lyric->getSiblings()->count() + 1 == $sl_group->count()) {
                foreach ($sl_group as $sl_object) {
                    $sibling = SongLyric::find($sl_object["id"]);
                    $sibling->update([
                        'type' => $sl_object["type"]
                    ]);
                }
            } else {
                // todo: validation error
                Log::error("situation 3 - error");
            }
        }
    }

    private function handleArrangementSourceUpdate($arrangement_source_data, $song_lyric)
    {
        // do this only if the song is an arrangement
        if ($song_lyric->is_arrangement) {
            $arrangement_source_id = $arrangement_source_data["update"]["id"];
            $arrangement_source = SongLyric::find($arrangement_source_id);

            if ($arrangement_source->is_arrangement) {
                // todo throw error
                return;
            }

            $song_lyric->arrangement_source()->associate($arrangement_source);
        }
    }
}
