@extends('admin.layouts.app')

@section('title', 'Modifier la Leçon : ' . $lesson->titre)

@php
    $currentModule = $lesson->module ?? ($lesson->chapter?->module ?? null);
    $pack = $currentModule?->pack;
@endphp

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Tableau de bord</a>
    <span>/</span>
    @if($pack)
        <a href="{{ route('admin.packs.show', $pack) }}">{{ $pack->nom }}</a>
        <span>/</span>
    @endif
    @if($currentModule)
        <a href="{{ route('admin.modules.show', $currentModule) }}">{{ $currentModule->nom }}</a>
        <span>/</span>
    @endif
    <span>Modifier : {{ $lesson->titre }}</span>
@endsection

@section('content')
<style>
    .lesson-form-container {
        max-width: 960px;
        margin: 0 auto;
    }

    .chapter-banner {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        border-radius: 16px;
        padding: 1.75rem;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 4px 14px rgba(30, 64, 175, 0.2);
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .chapter-icon-badge {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        background-color: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .course-parts-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-top: 1rem;
    }

    .part-card {
        border-radius: 14px;
        border: 2px solid #e2e8f0;
        background: #ffffff;
        padding: 1.5rem;
        transition: all 0.2s ease;
        position: relative;
    }

    .part-card.part-1 {
        border-left: 6px solid #3b82f6;
    }

    .part-card.part-2 {
        border-left: 6px solid #8b5cf6;
    }

    .part-card.part-3 {
        border-left: 6px solid #10b981;
    }

    .part-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .part-title-wrapper {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .part-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .part-badge.blue {
        background-color: #dbeafe;
        color: #1d4ed8;
    }

    .part-badge.purple {
        background-color: #ede9fe;
        color: #6d28d9;
    }

    .part-badge.green {
        background-color: #d1fae5;
        color: #047857;
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-pill {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .stat-pill-val {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1e40af;
        line-height: 1;
        margin-bottom: 0.35rem;
    }

    .stat-pill-lbl {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
    }

    .media-preview-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.78rem;
        background-color: #f1f5f9;
        color: #334155;
        padding: 0.25rem 0.6rem;
        border-radius: 6px;
        margin-top: 0.4rem;
    }
</style>

<div class="lesson-form-container">
    <!-- Module Context Banner -->
    <div class="chapter-banner">
        <div class="chapter-icon-badge">
            <i class="fas fa-edit"></i>
        </div>
        <div style="flex: 1;">
            <div style="font-size: 0.875rem; opacity: 0.85; margin-bottom: 0.25rem;">
                Pack : <strong>{{ $pack?->nom }}</strong> &bull; Module : <strong>{{ $currentModule?->nom }}</strong>
            </div>
            <h2 style="font-size: 1.35rem; font-weight: 700; margin: 0;">{{ $lesson->titre }}</h2>
            <div style="font-size: 0.85rem; opacity: 0.9; margin-top: 0.25rem;">
                Position #{{ $lesson->ordre }} dans le module
            </div>
        </div>
        @if($currentModule)
            <a href="{{ route('admin.modules.show', $currentModule) }}" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); border: none; color: white;">
                <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i> Retour
            </a>
        @endif
    </div>

    <!-- Stats Quick Cards -->
    <div class="stats-row">
        <div class="stat-pill">
            <div class="stat-pill-val">{{ $lesson->duree_minutes }} min</div>
            <div class="stat-pill-lbl">Durée estimée</div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-val" style="color: #8b5cf6;">{{ $lesson->progress->count() }}</div>
            <div class="stat-pill-lbl">Apprenants engagés</div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-val" style="color: #10b981;">{{ $lesson->progress->where('completed', true)->count() }}</div>
            <div class="stat-pill-lbl">Apprenants ayant terminé</div>
        </div>
    </div>

    <form action="{{ route('admin.lessons.update', $lesson) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-card" style="margin-bottom: 2rem;">
            <!-- Paramètres Généraux -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <div class="section-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    1. Paramètres de la Leçon
                </h3>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Titre de la leçon <span class="required">*</span></label>
                        <input type="text" 
                               name="titre" 
                               class="form-control @error('titre') error @enderror" 
                               value="{{ old('titre', $lesson->titre) }}" 
                               required>
                        @error('titre')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Durée estimée (minutes) <span class="required">*</span></label>
                        <input type="number" 
                               name="duree_minutes" 
                               class="form-control @error('duree_minutes') error @enderror" 
                               value="{{ old('duree_minutes', $lesson->duree_minutes) }}" 
                               min="1" 
                               required>
                        <div class="form-help">Temps global pour les 3 parties</div>
                        @error('duree_minutes')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ordre d'affichage <span class="required">*</span></label>
                        <input type="number" 
                               name="ordre" 
                               class="form-control @error('ordre') error @enderror" 
                               value="{{ old('ordre', $lesson->ordre) }}" 
                               min="0" 
                               required>
                        <div class="form-help">Ordre de déroulement dans le chapitre</div>
                        @error('ordre')
                            <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Architecture E-Learning en 3 Parties -->
            <div class="form-section" style="border-top: 1px solid #e2e8f0; padding-top: 2rem;">
                <h3 class="form-section-title">
                    <div class="section-icon" style="background-color: #ede9fe; color: #7c3aed;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    2. Contenu E-Learning en 3 Parties
                </h3>

                <div class="course-parts-grid">
                    <!-- PARTIE 1 : Théorie & Support Écrit -->
                    <div class="part-card part-1">
                        <div class="part-header">
                            <div class="part-title-wrapper">
                                <span class="part-badge blue">Partie 1</span>
                                <h4 style="margin: 0; font-size: 1.1rem; color: #1e293b; font-weight: 700;">
                                    <i class="fas fa-book-open" style="color: #3b82f6; margin-right: 0.35rem;"></i>
                                    Théorie & Support Écrit
                                </h4>
                            </div>
                            <span style="font-size: 0.8rem; color: #64748b;">Texte Markdown & Ressource Web</span>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label">Contenu Théorique / Cours Écrit</label>
                            <textarea name="contenu_texte" 
                                      rows="8" 
                                      class="form-control @error('contenu_texte') error @enderror" 
                                      placeholder="Rédigez le cours théorique ici (Markdown supporté)...">{{ old('contenu_texte', $lesson->contenu_texte) }}</textarea>
                            <div class="form-help">Base théorique lue par l'étudiant dans l'application mobile et web.</div>
                            @error('contenu_texte')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Lien Ressource Web / Documentation externe (URL Web)</label>
                            <input type="url" 
                                   name="url_web" 
                                   class="form-control @error('url_web') error @enderror" 
                                   value="{{ old('url_web', $lesson->url_web) }}" 
                                   placeholder="https://docs.google.com/... ou https://...">
                            @if($lesson->url_web)
                                <div class="media-preview-tag">
                                    <i class="fas fa-external-link-alt" style="color: #3b82f6;"></i>
                                    <a href="{{ $lesson->url_web }}" target="_blank" style="color: inherit; text-decoration: underline;">Tester le lien Web actuel</a>
                                </div>
                            @endif
                            @error('url_web')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- PARTIE 2 : Vidéo d'Explication Conceptuelle -->
                    <div class="part-card part-2">
                        <div class="part-header">
                            <div class="part-title-wrapper">
                                <span class="part-badge purple">Partie 2</span>
                                <h4 style="margin: 0; font-size: 1.1rem; color: #1e293b; font-weight: 700;">
                                    <i class="fas fa-play-circle" style="color: #8b5cf6; margin-right: 0.35rem;"></i>
                                    Vidéo d'Explication (Fondements & Concepts)
                                </h4>
                            </div>
                            <span style="font-size: 0.8rem; color: #64748b;">Cours magistral vidéo</span>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">URL de la Vidéo d'Explication</label>
                            <input type="url" 
                                   name="url_video_explication" 
                                   class="form-control @error('url_video_explication') error @enderror" 
                                   value="{{ old('url_video_explication', $lesson->url_video_explication ?? $lesson->url_video) }}" 
                                   placeholder="https://www.youtube.com/watch?v=... ou lien MP4 / Vimeo">
                            @if($lesson->url_video_explication ?? $lesson->url_video)
                                <div class="media-preview-tag">
                                    <i class="fas fa-video" style="color: #8b5cf6;"></i>
                                    <a href="{{ route('admin.lessons.video-preview', [$lesson, 'explication']) }}" target="_blank" style="color: inherit; text-decoration: underline;">Tester la vidéo d'explication dans le lecteur</a>
                                </div>
                            @endif
                            <div class="form-help">Vidéo pédagogique expliquant les concepts fondamentaux de la leçon.</div>
                            @error('url_video_explication')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- PARTIE 3 : Vidéo Pratique & Atelier Démonstration -->
                    <div class="part-card part-3">
                        <div class="part-header">
                            <div class="part-title-wrapper">
                                <span class="part-badge green">Partie 3</span>
                                <h4 style="margin: 0; font-size: 1.1rem; color: #1e293b; font-weight: 700;">
                                    <i class="fas fa-laptop-code" style="color: #10b981; margin-right: 0.35rem;"></i>
                                    Vidéo de Pratique & Cas Concret
                                </h4>
                            </div>
                            <span style="font-size: 0.8rem; color: #64748b;">Démonstration métier</span>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">URL de la Vidéo Pratique</label>
                            <input type="url" 
                                   name="url_video_pratique" 
                                   class="form-control @error('url_video_pratique') error @enderror" 
                                   value="{{ old('url_video_pratique', $lesson->url_video_pratique) }}" 
                                   placeholder="https://www.youtube.com/watch?v=... ou lien MP4 / Vimeo">
                            @if($lesson->url_video_pratique)
                                <div class="media-preview-tag">
                                    <i class="fas fa-laptop-code" style="color: #10b981;"></i>
                                    <a href="{{ route('admin.lessons.video-preview', [$lesson, 'pratique']) }}" target="_blank" style="color: inherit; text-decoration: underline;">Tester la vidéo pratique dans le lecteur</a>
                                </div>
                            @endif
                            <div class="form-help">Démonstration concrète, atelier pratique, manipulation d'outils et cas réel.</div>
                            @error('url_video_pratique')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statut & Activation -->
            <div class="form-section" style="border-top: 1px solid #e2e8f0; padding-top: 2rem;">
                <h3 class="form-section-title">
                    <div class="section-icon" style="background-color: #d1fae5; color: #059669;">
                        <i class="fas fa-toggle-on"></i>
                    </div>
                    3. Visibilité
                </h3>

                <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background-color: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                    <input type="checkbox" name="active" id="active" value="1" {{ old('active', $lesson->active) ? 'checked' : '' }} style="width: 20px; height: 20px; accent-color: #2563eb;">
                    <label for="active" style="cursor: pointer; margin: 0;">
                        <div style="font-weight: 600; color: #1e293b;">Leçon active et accessible</div>
                        <div style="font-size: 0.85rem; color: #64748b;">Les apprenants inscrits pourront visionner cette leçon.</div>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions" style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <button type="button" 
                        class="btn btn-danger" 
                        onclick="if(confirm('Supprimer définitivement cette leçon ?')) document.getElementById('deleteLessonForm').submit();">
                    <i class="fas fa-trash" style="margin-right: 0.5rem;"></i> Supprimer la leçon
                </button>

                <div style="display: flex; gap: 0.75rem;">
                    <a href="{{ $currentModule ? route('admin.modules.show', $currentModule) : route('admin.packs.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times" style="margin-right: 0.5rem;"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        <i class="fas fa-save" style="margin-right: 0.5rem;"></i> Mettre à Jour (3 Parties)
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Separate Delete Form to prevent invalid HTML nested forms -->
    <form id="deleteLessonForm" action="{{ route('admin.lessons.destroy', $lesson) }}" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection
