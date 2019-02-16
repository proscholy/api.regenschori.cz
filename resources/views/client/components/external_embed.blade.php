@if($external->type == 0)
    <div class="card" style="margin-bottom: 1em;">
        <div class="card-header">
            <i style="color: #db0e0e;" class="fab fa-youtube"></i>
            @if(isset($external->author_id) and isset($external->song_lyric_id))
                {!! $external->author->getLink() !!} - <a
                        href="{{$external->songLyric->getLink()}}">{{$external->songLyric->name}}</a>
            @endif
        </div>

        <div class="embed-responsive embed-responsive-16by9">

            {{--<iframe src="{{$external->getEmbedUrl()}}?showinfo=0" frameborder="0"--}}
            {{--allowfullscreen></iframe>--}}
            <iframe src="{{$external->getEmbedUrl()}}" frameborder="0"
                    allowfullscreen></iframe>
        </div>
    </div>
    @else
    <div class="card" style="margin-bottom: 1em;">Wrong external media type.</div>
@endif