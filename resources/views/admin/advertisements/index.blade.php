@extends('admin.layouts.app')

@section('title', 'Publicités mobile')

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
    <div>
        <h1>Publicités mobile</h1>
        <p>Une publicité active est proposée une fois sur chaque appareil.</p>
    </div>
    <a href="{{ route('admin.advertisements.create') }}" class="btn btn-primary">Nouvelle publicité</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="content-card" style="overflow-x:auto">
    <table class="table" style="width:100%">
        <thead>
            <tr>
                <th>Bannière</th>
                <th>Titre</th>
                <th>Redirection</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($advertisements as $advertisement)
                <tr>
                    <td><img src="{{ $advertisement->banner_url }}" alt="" style="width:140px;height:72px;object-fit:cover;border-radius:8px"></td>
                    <td>{{ $advertisement->title ?: 'Sans titre' }}</td>
                    <td><a href="{{ $advertisement->redirect_url }}" target="_blank" rel="noopener">{{ $advertisement->redirect_url }}</a></td>
                    <td>{{ $advertisement->active ? 'Active' : 'Inactive' }}</td>
                    <td style="white-space:nowrap">
                        @if($advertisement->active)
                            <form action="{{ route('admin.advertisements.deactivate', $advertisement) }}" method="POST" style="display:inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-secondary">Désactiver</button>
                            </form>
                        @endif
                        <form action="{{ route('admin.advertisements.destroy', $advertisement) }}" method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette publicité ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:32px">Aucune publicité configurée.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $advertisements->links() }}
</div>
@endsection
