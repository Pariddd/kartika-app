@props(['post', 'masked' => false, 'showNames' => false])

@php
    use App\Support\ContentMask;
    // Admin dan analis melihat teks dengan @username asli bila tersimpan; selain itu teks tersamar.
    $text = $post === null ? null : ($showNames && $post->text_raw !== null ? $post->text_raw : $post->text);
@endphp

@if ($masked)
    <p class="text-[15px] italic text-muted">[teks disamarkan · mode presentasi]</p>
@elseif ($post === null)
    <p class="text-[15px] italic text-muted">Post tidak ditemukan.</p>
@elseif (ContentMask::display($text) === ContentMask::PLACEHOLDER)
    <p class="text-[15px] italic text-muted">{{ ContentMask::PLACEHOLDER }} <span class="text-xs not-italic">· memuat kata dari daftar penyamaran</span></p>
@else
    <p class="text-[15px] leading-relaxed">{{ $text }}</p>
@endif
