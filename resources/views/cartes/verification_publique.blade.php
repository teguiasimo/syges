<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrôle d'Authenticité Carte Scolaire - SYGES</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 16px;
        }
        .container {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .status-header {
            padding: 24px 20px;
            text-align: center;
            color: #ffffff;
        }
        .status-header.valid {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
        }
        .status-header.invalid {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
        }
        .status-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            font-size: 28px;
        }
        .status-title {
            font-size: 19px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .status-subtitle {
            font-size: 13px;
            opacity: 0.92;
            font-weight: 500;
        }
        .card-body {
            padding: 24px 20px;
        }
        .school-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f1f5f9;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        .school-logo {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: contain;
            background: #fff;
            padding: 2px;
            border: 1px solid #cbd5e1;
        }
        .school-name {
            font-size: 13px;
            font-weight: 700;
            color: #0369a1;
            line-height: 1.3;
        }
        .school-sub {
            font-size: 11px;
            color: #64748b;
        }
        .student-profile {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }
        .student-photo-wrapper {
            width: 80px;
            height: 100px;
            border-radius: 8px;
            border: 1.5px solid #cbd5e1;
            overflow: hidden;
            background: #f8fafc;
            flex-shrink: 0;
            text-align: center;
        }
        .student-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-fallback {
            line-height: 100px;
            font-size: 11px;
            color: #94a3b8;
            font-weight: bold;
        }
        .student-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .student-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
            line-height: 1.25;
        }
        .student-matricule {
            display: inline-block;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            margin-bottom: 6px;
            border: 1px solid #fecaca;
        }
        .student-classe {
            font-size: 13px;
            color: #475569;
            font-weight: 600;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .data-table td {
            padding: 8px 4px;
            border-bottom: 1px solid #f1f5f9;
        }
        .data-label {
            color: #64748b;
            font-weight: 500;
            width: 42%;
        }
        .data-val {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
        .footer {
            padding: 16px 20px;
            background: #fafafa;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }
        .badge-verified {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #dcfce7;
            color: #15803d;
            padding: 4px 10px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 11px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        @if($valide && $inscription)
            <div class="status-header valid">
                <div class="status-icon">✓</div>
                <div class="status-title">Carte Valide & Authentique</div>
                <div class="status-subtitle">Élève régulièrement inscrit dans l'établissement</div>
            </div>

            <div class="card-body">
                <div class="school-badge">
                    @if(!empty($inscription->etablissement->logo) && file_exists(public_path($inscription->etablissement->logo)))
                        <img src="{{ asset($inscription->etablissement->logo) }}" class="school-logo" alt="Logo">
                    @else
                        <div class="school-logo" style="display: flex; align-items: center; justify-content: center; font-weight: bold; color: #0284c7;">★</div>
                    @endif
                    <div>
                        <div class="school-name">{{ $inscription->etablissement->nom }}</div>
                        <div class="school-sub">Année Scolaire {{ $inscription->anneeScolaire->nom }}</div>
                    </div>
                </div>

                <div class="student-profile">
                    <div class="student-photo-wrapper">
                        @if(!empty($eleve->photo_url) && file_exists(public_path($eleve->photo_url)))
                            <img src="{{ asset($eleve->photo_url) }}" class="student-photo" alt="Photo">
                        @else
                            <div class="photo-fallback">PHOTO</div>
                        @endif
                    </div>
                    <div class="student-details">
                        <div class="student-matricule">MAT: {{ $eleve->matricule }}</div>
                        <div class="student-name">{{ $eleve->nom }} {{ $eleve->prenom }}</div>
                        <div class="student-classe">Classe : {{ $inscription->classe->nom }}</div>
                    </div>
                </div>

                <div style="text-align: center;">
                    <span class="badge-verified">✓ Statut : Régulièrement Inscrit(e)</span>
                </div>

                <table class="data-table">
                    <tr>
                        <td class="data-label">Date de naissance :</td>
                        <td class="data-val">{{ $eleve->date_naissance ? $eleve->date_naissance->format('d/m/Y') : '---' }}</td>
                    </tr>
                    <tr>
                        <td class="data-label">Lieu de naissance :</td>
                        <td class="data-val">{{ $eleve->lieu_naissance ?: '---' }}</td>
                    </tr>
                    <tr>
                        <td class="data-label">Sexe :</td>
                        <td class="data-val">{{ $eleve->sexe === 'F' ? 'Féminin' : 'Masculin' }}</td>
                    </tr>
                    <tr>
                        <td class="data-label">Contact Parent / Tuteur :</td>
                        <td class="data-val">{{ $eleve->telephone_parent ?: 'Non renseigné' }}</td>
                    </tr>
                    <tr>
                        <td class="data-label">Dernière vérification :</td>
                        <td class="data-val">{{ now()->format('d/m/Y H:i:s') }}</td>
                    </tr>
                </table>
            </div>
        @else
            <div class="status-header invalid">
                <div class="status-icon">✕</div>
                <div class="status-title">Carte Non Reconnue</div>
                <div class="status-subtitle">{{ $message }}</div>
            </div>
            <div class="card-body" style="text-align: center;">
                <p style="font-size: 13px; color: #64748b; margin-top: 10px;">
                    Veuillez contacter le secrétariat de l'établissement ou la surveillance générale pour régulariser la situation de cet élève.
                </p>
            </div>
        @endif

        <div class="footer">
            SYGES Académique — Plateforme de Gestion Scolaire Sécurisée
        </div>
    </div>
</body>
</html>
