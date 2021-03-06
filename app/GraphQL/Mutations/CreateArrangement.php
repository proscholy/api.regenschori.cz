<?php

namespace App\GraphQL\Mutations;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Illuminate\Support\Facades\Auth;
// use Validator;
use Validator;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Execution\ErrorBuffer;

use App\Author;
use App\SongLyric;
use App\Song;
use App\External;
use App\Songbook;

class CreateArrangement
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
        // $input = $args["input"];

        return SongLyric::create([
            'name' => $args["name"],
            'arrangement_of' => $args["arrangement_of"],
            'type' => null,
            'song_id' => null // arrangement has no `song` - meaning belongs to no song group per definition
        ]);
    }
}
