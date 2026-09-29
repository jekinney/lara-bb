@extends('install.layout')
@section('content')
<h1>Ready to install</h1>
<p class="lead">Check these details. Installing creates the tables and your account, writes the configuration, and locks this installer.</p>
@php(['database' => $db, 'services' => $svc, 'site' => $site] = $summary)
<dl>
    <dt>Board</dt><dd>{{ $site['board_name'] }} at {{ $site['board_url'] }}</dd>
    <dt>Database</dt><dd>{{ $db['driver'] }}{{ $db['driver'] === 'sqlite' ? '' : ' at '.$db['host'].':'.$db['port'] }}, {{ $db['database'] }}</dd>
    <dt>Cache and queue</dt><dd>{{ $svc['cache_driver'] === 'redis' ? 'Redis at '.$svc['redis_host'] : 'Database' }}</dd>
    <dt>Mail</dt><dd>{{ $svc['mail_mailer'] === 'smtp' ? 'SMTP at '.$svc['mail_host'] : 'Written to the log' }}</dd>
    <dt>File storage</dt><dd>{{ $svc['storage'] === 's3' ? 'S3 bucket '.$svc['s3_bucket'] : 'Local volume' }}</dd>
    <dt>Editor</dt><dd>{{ $site['editor'] === 'markdown' ? 'Markdown' : 'BBCode' }}</dd>
    <dt>Founder</dt><dd>{{ $site['founder_username'] }} ({{ $site['founder_email'] }})</dd>
</dl>
@error('install')<div class="banner bad" role="alert">{{ $message }}</div>@enderror
<form method="post" action="{{ route('install.run') }}" class="row">
    @csrf
    <a class="btn" href="{{ route('install.site') }}">Back</a>
    <button class="btn pri" type="submit">Install laraBB</button>
</form>
@endsection
