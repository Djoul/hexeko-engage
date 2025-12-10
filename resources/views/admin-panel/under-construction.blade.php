@extends('admin-panel.layouts.app')

@section('page-content')
{{--    <livewire:admin-panel.under-construction--}}
{{--        :title="$title"--}}
{{--        :description="$description"--}}
{{--        :expectedDate="$expectedDate ?? null"--}}
{{--        :features="$features ?? []"--}}
{{--    />--}}
<h1 class="text-3xl font-bold text-neutral-900 mb-6">Page en cours de développement</h1>

<div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6 border-l-4 border-l-info-400 bg-info-50">
    <div class="text-lg font-medium text-neutral-900 mb-2">🚧 Construction en cours</div>
    <p>Cette page est actuellement en cours de développement. Revenez bientôt pour découvrir son contenu !</p>
</div>

<p>Nous travaillons activement sur cette section de la admin-panel. En attendant :</p>

<ul>
    <li>Consultez la <a href="{{ route('admin.index') }}">page d'accueil</a> pour une vue d'ensemble</li>
    <li>Explorez les autres sections disponibles dans le menu de navigation</li>
    <li>Contactez l'équipe si vous avez des questions urgentes</li>
</ul>

<div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6 border-l-4 border-l-warning-400 bg-warning-50" style="margin-top: 40px;">
    <div class="text-lg font-medium text-neutral-900 mb-2">💡 Besoin d'aide ?</div>
    <p>Si vous cherchez des informations spécifiques qui devraient se trouver sur cette page, n'hésitez pas à créer une issue sur GitLab pour nous le signaler.</p>
</div>
@endsection
