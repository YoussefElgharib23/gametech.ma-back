<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
      .muted { color: #6b7280; }
      .header { width: 100%; margin-bottom: 18px; }
      .header td { vertical-align: middle; }
      .logo { width: 140px; }
      h1 { font-size: 18px; margin: 0; }
      .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
      table.items { width: 100%; border-collapse: collapse; margin-top: 14px; }
      table.items th { text-align: left; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
      table.items td { padding: 8px; border: 1px solid #e5e7eb; }
      .right { text-align: right; }
      table.product { border-collapse: collapse; }
      table.product td { border: none; padding: 0; vertical-align: middle; }
      .thumb { width: 42px; height: 42px; border: 1px solid #e5e7eb; border-radius: 6px; background: #f3f4f6; object-fit: contain; }
      .thumb-wrap { width: 52px; padding-right: 10px; }
      .total { margin-top: 12px; width: 100%; }
      .total td { padding: 6px 0; }
      .grand { font-weight: 700; font-size: 14px; }
      .footer { margin-top: 22px; font-size: 10px; color: #6b7280; }
    </style>
  </head>
  <body>
    <table class="header">
      <tr>
        <td style="width: 45%;">
          @if($logoPath)
            <img src="file://{{ $logoPath }}" class="logo" alt="Logo" />
          @else
            <div class="muted">{{ $site['name'] }}</div>
          @endif
        </td>
        <td style="width: 55%;" class="right">
          <h1>Devis</h1>
          <div class="muted">Généré le {{ $generatedAt->format('d/m/Y H:i') }}</div>
          <div class="muted">{{ $site['url'] }}</div>
        </td>
      </tr>
    </table>

    <div class="box">
      <div><strong>{{ $site['name'] }}</strong></div>
      <div class="muted">Devis informatif — prix susceptibles d’évoluer selon disponibilité et promotions.</div>
    </div>

    <div class="box" style="margin-top: 10px;">
      <div style="font-weight: 700; margin-bottom: 6px;">Informations client</div>
      <table style="width: 100%; border-collapse: collapse;">
        <tr>
          <td style="width: 20%;" class="muted">Nom</td>
          <td>{{ $customer['name'] ?? '' }}</td>
        </tr>
        <tr>
          <td class="muted">Email</td>
          <td>{{ $customer['email'] ?? '' }}</td>
        </tr>
        <tr>
          <td class="muted">Téléphone</td>
          <td>{{ $customer['phone'] ?? '' }}</td>
        </tr>
        <tr>
          <td class="muted" style="vertical-align: top; padding-top: 6px;">Adresse</td>
          <td style="white-space: pre-line; padding-top: 6px;">{{ $customer['address'] ?? '' }}</td>
        </tr>
      </table>
    </div>

    <table class="items">
      <thead>
        <tr>
          <th>Produit</th>
          <th style="width: 10%;" class="right">Qté</th>
          <th style="width: 15%;" class="right">PU</th>
          <th style="width: 15%;" class="right">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($lines as $line)
          <tr>
            <td>
              <table class="product">
                <tr>
                  <td class="thumb-wrap">
                    @if(!empty($line['image_path']))
                      <img src="file://{{ $line['image_path'] }}" class="thumb" alt="Produit" />
                    @else
                      <div class="thumb"></div>
                    @endif
                  </td>
                  <td>{{ $line['title'] }}</td>
                </tr>
              </table>
            </td>
            <td class="right">{{ $line['quantity'] }}</td>
            <td class="right">{{ number_format((float) $line['unit_price'], 2, '.', ' ') }} {{ $currency }}</td>
            <td class="right">{{ number_format((float) $line['line_total'], 2, '.', ' ') }} {{ $currency }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <table class="total">
      <tr>
        <td class="right grand">Total : {{ number_format((float) $total, 2, '.', ' ') }} {{ $currency }}</td>
      </tr>
    </table>

    <div class="footer">
      Document généré automatiquement par {{ $site['name'] }}.
    </div>
  </body>
</html>

