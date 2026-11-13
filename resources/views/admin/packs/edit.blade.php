@extends('admin.layouts.app')

@section('title', 'Modifier le Pack')

@push('styles')
<style>
    .form-card {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .form-header {
        padding: 1.5rem 2rem;
        border-bottom: 2px solid var(--gray-100);
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
        color: var(--white);
    }

    .form-header h2 {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-header svg {
        width: 24px;
        height: 24px;
    }

    .form-body {
        padding: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-input {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        transition: all 0.2s ease;
        background: var(--white);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-input.error {
        border-color: #ef4444;
    }

    .form-input.error:focus {
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    .form-select {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        background: var(--white);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .form-select:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        background: var(--white);
        resize: vertical;
        min-height: 100px;
        transition: all 0.2s ease;
    }

    .form-textarea:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .error-message {
        color: #ef4444;
        font-size: 0.813rem;
        margin-top: 0.375rem;
        display: block;
        font-weight: 500;
    }

    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .checkbox-item:hover {
        background: var(--gray-50);
        border-color: var(--primary-blue);
    }

    .checkbox-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        margin-right: 0.75rem;
        cursor: pointer;
    }

    .checkbox-item input[type="checkbox"]:checked {
        background-color: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .checkbox-item label {
        flex: 1;
        cursor: pointer;
        font-size: 0.875rem;
        color: var(--gray-700);
    }

    .debouches-container {
        margin-top: 1rem;
    }

    .debouche-item {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
        align-items: center;
    }

    .btn-add-debouche {
        margin-top: 0.75rem;
        background: var(--primary-blue);
        color: var(--white);
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.813rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: background-color 0.2s ease;
    }

    .btn-add-debouche:hover {
        background: #2563eb;
    }

    .btn-add-debouche svg {
        width: 16px;
        height: 16px;
    }

    .btn-remove-debouche {
        background: #ef4444;
        color: var(--white);
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s ease;
    }

    .btn-remove-debouche:hover {
        background: #dc2626;
    }

    .btn-remove-debouche svg {
        width: 16px;
        height: 16px;
    }

    .profiles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.75rem;
        max-height: 200px;
        overflow-y: auto;
        padding: 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        background: var(--white);
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--gray-200);
    }

    .btn-cancel {
        padding: 0.75rem 1.5rem;
        background: var(--gray-200);
        color: var(--gray-700);
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-cancel:hover {
        background: var(--gray-300);
        transform: translateY(-1px);
    }

    .btn-submit {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--success-green) 100%);
        color: var(--white);
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .alert-error {
        background-color: #fee2e2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .alert-error svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        margin-top: 0.125rem;
    }

    .error-list {
        margin: 0;
        padding-left: 1.25rem;
    }

    .error-list li {
        margin-bottom: 0.25rem;
        font-size: 0.875rem;
    }

    @media (max-width: 768px) {
        .form-body {
            padding: 1.5rem;
        }

        .profiles-grid {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn-cancel,
        .btn-submit {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1 class="page-title">Modifier le Pack</h1>
    <p class="page-description">Mettez à jour les informations du pack "{{ $pack->nom }}"</p>
</div>

@if($errors->any())
<div class="alert-error">
    <svg fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
    </svg>
    <div>
        <h3 style="font-weight: 600; margin: 0 0 0.5rem 0;">Erreurs dans le formulaire :</h3>
        <ul class="error-list">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<form action="{{ route('admin.packs.update', $pack) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @method('PUT')

    <div class="form-card">
        <div class="form-header"><h2>Bannière du pack</h2></div>
        <div class="form-body">
            @if($pack->banner_path)
                <div class="form-group">
                    <img src="{{ asset('storage/'.$pack->banner_path) }}" alt="Bannière actuelle du pack" style="max-width:100%;max-height:220px;border-radius:10px">
                </div>
            @endif
            <div class="form-group">
                <label for="banner" class="form-label">Remplacer la bannière (facultatif)</label>
                <input type="file" name="banner" id="banner" accept="image/jpeg,image/png,image/webp" class="form-input">
                <small>JPG, PNG ou WEBP · 8 Mo maximum.</small>
                @error('banner') <div class="error-message">{{ $message }}</div> @enderror
            </div>
            @if($pack->banner_path)
                <label class="checkbox-item">
                    <input type="checkbox" name="remove_banner" value="1">
                    <span>Supprimer la bannière actuelle</span>
                </label>
            @endif
        </div>
    </div>

    <!-- Informations de base -->
    <div class="form-card">
        <div class="form-header">
            <h2>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                Informations de Base
            </h2>
        </div>
        <div class="form-body">
            <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label for="nom" class="form-label">
                        Nom du Pack <span class="required">*</span>
                    </label>
                    <input type="text" 
                           name="nom" 
                           id="nom" 
                           value="{{ old('nom', $pack->nom) }}"
                           class="form-input @error('nom') error @enderror"
                           placeholder="Ex: Secrétariat Bureautique"
                           required>
                    @error('nom') <div class="error-message">{{ $message }}</div> @enderror
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label for="prix_fcfa" class="form-label">Prix réel (FCFA)</label>
                        <input type="number" name="prix_fcfa" id="prix_fcfa" min="0" step="0.01" value="{{ old('prix_fcfa', $pack->prix_fcfa) }}" class="form-input @error('prix_fcfa') error @enderror" placeholder="Ex: 135000">
                        @error('prix_fcfa') <div class="error-message">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="category_id" class="form-label">
                            Catégorie <span class="required">*</span>
                        </label>
                        <select name="category_id" 
                                id="category_id" 
                                class="form-select @error('category_id') error @enderror"
                                required>
                            <option value="">Sélectionnez une catégorie</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $pack->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->nom }}
                            </option>
                            @endforeach
                        </select>
                        @error('category_id') <div class="error-message">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="niveau_requis" class="form-label">
                            Niveau Requis <span class="required">*</span>
                        </label>
                        <select name="niveau_requis" 
                                id="niveau_requis" 
                                class="form-select @error('niveau_requis') error @enderror"
                                required>
                            <option value="">Sélectionnez un niveau</option>
                            @foreach(['BEPC', 'Probatoire', 'BAC', 'Licence', 'Master'] as $niveau)
                            <option value="{{ $niveau }}" {{ old('niveau_requis', $pack->niveau_requis) == $niveau ? 'selected' : '' }}>
                                {{ $niveau }}
                            </option>
                            @endforeach
                        </select>
                        @error('niveau_requis') <div class="error-message">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="prix_points" class="form-label">
                            Prix (Points) <span class="required">*</span>
                        </label>
                        <input type="number" 
                               name="prix_points" 
                               id="prix_points" 
                               value="{{ old('prix_points', $pack->prix_points) }}"
                               min="1"
                               class="form-input @error('prix_points') error @enderror"
                               required>
                        @error('prix_points') <div class="error-message">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Statut</label>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" 
                                   name="active" 
                                   id="active" 
                                   value="1"
                                   {{ old('active', $pack->active) ? 'checked' : '' }}
                                   style="width: 18px; height: 18px;">
                            <label for="active" style="font-size: 0.875rem; color: var(--gray-700); cursor: pointer;">
                                Pack actif
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">
                        Description
                    </label>
                    <textarea name="description" 
                              id="description" 
                              rows="4"
                              class="form-textarea @error('description') error @enderror"
                              placeholder="Décrivez le contenu du pack...">{{ old('description', $pack->description) }}</textarea>
                    @error('description') <div class="error-message">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <!-- Durées et Diplômes -->
    <div class="form-card">
        <div class="form-header">
            <h2>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z"/>
                </svg>
                Durées et Diplômes
            </h2>
        </div>
@php
    $dureesSelected = old('durees_disponibles', $pack->durees_disponibles ?? []);
    if (is_string($dureesSelected)) {
        $dureesSelected = json_decode($dureesSelected, true) ?? explode(',', $dureesSelected);
    }
    if (!is_array($dureesSelected)) {
        $dureesSelected = [];
    }

    $diplomesSelected = old('diplomes_possibles', $pack->diplomes_possibles ?? []);
    if (is_string($diplomesSelected)) {
        $diplomesSelected = json_decode($diplomesSelected, true) ?? explode(',', $diplomesSelected);
    }
    if (!is_array($diplomesSelected)) {
        $diplomesSelected = [];
    }
@endphp
        <div class="form-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="form-label">
                        Durées Disponibles <span class="required">*</span>
                    </label>
                    <div class="checkbox-group">
                        @foreach(['3 mois', '6 mois', '9 mois', '12 mois', '18 mois', '24 mois'] as $duree)
                        <div class="checkbox-item">
                            <input type="checkbox" 
                                   name="durees_disponibles[]" 
                                   value="{{ $duree }}"
                                   id="duree_{{ $loop->index }}"
                                   {{ in_array($duree, $dureesSelected) ? 'checked' : '' }}>
                            <label for="duree_{{ $loop->index }}">{{ $duree }}</label>
                        </div>
                        @endforeach
                    </div>
                    @error('durees_disponibles') <div class="error-message">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Diplômes Possibles (Optionnel)
                    </label>
                    <div class="checkbox-group">
                        @foreach(['AQP', 'CQP', 'DQP', 'BTS', 'Licence Professionnelle'] as $diplome)
                        <div class="checkbox-item">
                            <input type="checkbox" 
                                   name="diplomes_possibles[]" 
                                   value="{{ $diplome }}"
                                   id="diplome_{{ $loop->index }}"
                                   {{ in_array($diplome, $diplomesSelected) ? 'checked' : '' }}>
                            <label for="diplome_{{ $loop->index }}">{{ $diplome }}</label>
                        </div>
                        @endforeach
                    </div>
                    @error('diplomes_possibles') <div class="error-message">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <!-- Débouchés et Profils -->
    <div class="form-card">
        <div class="form-header">
            <h2>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Débouchés et Profils
            </h2>
        </div>
        <div class="form-body">
            <div class="form-group">
                <label class="form-label">Débouchés Professionnels</label>
                <div id="debouches-container">
                    <div class="space-y-2" id="debouches-list">
                        @php
                            $oldDebouches = old('debouches');
                            $currentDebouches = $pack->debouches ?? [];
                            if (is_string($currentDebouches)) {
                                $currentDebouches = json_decode($currentDebouches, true) ?? explode(',', $currentDebouches);
                            }
                            if (!is_array($currentDebouches)) {
                                $currentDebouches = [];
                            }
                            if (is_string($oldDebouches)) {
                                $oldDebouches = json_decode($oldDebouches, true) ?? explode(',', $oldDebouches);
                            }
                            if (!is_array($oldDebouches) && $oldDebouches !== null) {
                                $oldDebouches = (array) $oldDebouches;
                            }
                        @endphp
                        
                        @if(is_array($oldDebouches) && count($oldDebouches) > 0)
                            @foreach($oldDebouches as $index => $debouche)
                            <div class="debouche-item">
                                <input type="text" 
                                       name="debouches[]" 
                                       value="{{ $debouche }}"
                                       class="form-input"
                                       placeholder="Ex: Secrétaire de direction">
                                <button type="button" 
                                        onclick="removeDebouche(this)"
                                        class="btn-remove-debouche">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                            @endforeach
                        @elseif(is_array($currentDebouches) && count($currentDebouches) > 0)
                            @foreach($currentDebouches as $debouche)
                            <div class="debouche-item">
                                <input type="text" 
                                       name="debouches[]" 
                                       value="{{ $debouche }}"
                                       class="form-input"
                                       placeholder="Ex: Secrétaire de direction">
                                <button type="button" 
                                        onclick="removeDebouche(this)"
                                        class="btn-remove-debouche">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                            @endforeach
                        @else
                            <div class="debouche-item">
                                <input type="text" 
                                       name="debouches[]" 
                                       class="form-input"
                                       placeholder="Ex: Secrétaire de direction">
                                <button type="button" 
                                        onclick="removeDebouche(this)"
                                        class="btn-remove-debouche">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        @endif
                    </div>
                    <button type="button" 
                            onclick="addDebouche()"
                            class="btn-add-debouche">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ajouter un débouché
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Profils de Carrière Associés</label>
                <div class="profiles-grid">
                    @php
                        $profilesSelected = old('profiles');
                        if ($profilesSelected === null) {
                            $profilesSelected = $pack->relationLoaded('profiles') || $pack->profiles 
                                ? $pack->profiles->pluck('id')->toArray() 
                                : [];
                        }
                        $profilesSelected = (array) $profilesSelected;
                    @endphp
                    @foreach($profiles as $profile)
                    <div class="checkbox-item">
                        <input type="checkbox" 
                               name="profiles[]" 
                               value="{{ $profile->id }}"
                               id="profile_{{ $profile->id }}"
                               {{ in_array($profile->id, $profilesSelected) ? 'checked' : '' }}>
                        <label for="profile_{{ $profile->id }}">{{ $profile->nom }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="form-actions">
        <a href="{{ route('admin.packs.show', $pack) }}" class="btn-cancel">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Annuler
        </a>
        <button type="submit" class="btn-submit">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Mettre à Jour
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function addDebouche() {
    const container = document.getElementById('debouches-list');
    const div = document.createElement('div');
    div.className = 'debouche-item';
    div.innerHTML = `
        <input type="text" 
               name="debouches[]" 
               class="form-input"
               placeholder="Ex: Secrétaire de direction">
        <button type="button" 
                onclick="removeDebouche(this)"
                class="btn-remove-debouche">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>
    `;
    container.appendChild(div);
}

function removeDebouche(button) {
    const container = document.getElementById('debouches-list');
    if (container.children.length > 1) {
        button.closest('.debouche-item').remove();
    }
}
</script>
@endpush