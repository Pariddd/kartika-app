@props(['post', 'masked' => false])

@php use App\Support\ContentMask; @endphp

@if ($masked)
    <p class="text-[15px] italic text-muted">[teks disamarkan · mode presentasi]</p>
@elseif ($post === null)
    <p class="text-[15px] italic text-muted">Post tidak ditemukan.</p>
@elseif (ContentMask::display($post->text) === ContentMask::PLACEHOLDER)
    <p class="text-[15px] italic text-muted">{{ ContentMask::PLACEHOLDER }} <span class="text-xs not-italic">· memuat kata dari daftar penyamaran</span></p>
@else
    <p class="text-[15px] leading-relaxed">{{ $post->text }}</p>
@endif
