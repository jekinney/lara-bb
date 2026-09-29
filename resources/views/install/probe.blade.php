@if (session('probe') && session('probe.section') === $section)
    <div class="banner {{ session('probe.ok') ? 'ok' : 'bad' }}" role="status">{{ session('probe.message') }}</div>
@endif
