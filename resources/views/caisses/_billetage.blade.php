{{-- Saisie du nombre de billets par coupure ; les totaux sont calculés par le serveur. --}}
@error('billetage') <div class="alert alert-danger" role="alert">{{ $message }}</div> @enderror
<div class="row g-4">
    @foreach (App\Domain\Caisse\Billetage::COUPURES as $devise => $coupures)
        <div class="col-md-6">
            <h3 class="h6">{{ $devise }}</h3>
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Billet</th><th class="w-50">Nombre</th></tr></thead>
                <tbody>
                    @foreach (array_reverse($coupures) as $coupure)
                        <tr>
                            <td>{{ number_format($coupure, 0, ',', ' ') }} {{ $devise }}</td>
                            <td>
                                <input type="number" min="0" step="1" inputmode="numeric" class="form-control form-control-sm"
                                       name="billetage[{{ $devise }}][{{ $coupure }}]" value="{{ old("billetage.$devise.$coupure") }}"
                                       aria-label="Nombre de billets de {{ $coupure }} {{ $devise }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
