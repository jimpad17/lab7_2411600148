{{--
    Lab 7 - reusable filter toolbar shell. Views provide their own fields in
    the slot; the action, spacing and Bootstrap grid stay consistent everywhere.
--}}
<div class="card mb-4 shadow-sm bg-white p-2">
    <div class="card-body">
        <form method="GET" action="{{ $action }}" class="row g-3 align-items-end">
            {{ $slot }}
        </form>
    </div>
</div>
