@extends('admin.layouts.app')

@section('title', 'Nouvelle publicité')

@section('content')
<div class="page-header">
    <h1>Nouvelle publicité mobile</h1>
    <p>La nouvelle campagne remplacera la publicité actuellement active sur le tableau de bord mobile.</p>
</div>

<div class="content-card" style="max-width:760px;padding:24px">
    <form action="{{ route('admin.advertisements.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group" style="margin-bottom:20px">
            <label for="title">Titre (facultatif)</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="255" class="form-control">
            @error('title') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="form-group" style="margin-bottom:20px">
            <label for="banner">Bannière</label>
            <input id="banner" name="banner" type="file" accept="image/jpeg,image/png,image/webp" required class="form-control">
            <small>JPG, PNG ou WEBP · 8 Mo maximum.</small>
            @error('banner') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="form-group" style="margin-bottom:24px">
            <label for="redirect_url">URL de redirection</label>
            <input id="redirect_url" name="redirect_url" type="url" value="{{ old('redirect_url') }}" required maxlength="2048" class="form-control" placeholder="https://...">
            @error('redirect_url') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <button type="submit" class="btn btn-primary">Publier la publicité</button>
        <a href="{{ route('admin.advertisements.index') }}" class="btn btn-secondary">Annuler</a>
    </form>
</div>
@endsection
