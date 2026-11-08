<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SIPUS') - SIPUS | Sistem Informasi Perpustakaan Digital</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #ebf3fc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #1a2f4c;
        }

        .auth-backdrop {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }

        .auth-backdrop::before,
        .auth-backdrop::after {
            content: '';
            position: absolute;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: rgba(58, 122, 212, 0.12);
            filter: blur(90px);
        }

        .auth-backdrop::before { top: -140px; left: -140px; }
        .auth-backdrop::after  { bottom: -160px; right: -140px; }

        .auth-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 1px solid #eaf0f8;
            border-radius: 28px;
            padding: 40px 34px 32px;
            text-align: center;
            box-shadow: 0 24px 60px rgba(28, 68, 124, 0.12);
        }

        .auth-card h2 {
            margin: 20px 0 8px;
            font-size: 26px;
            font-weight: 800;
            color: #17325c;
            letter-spacing: -0.5px;
        }

        .auth-card .lead {
            margin: 0 0 22px;
            font-size: 14px;
            line-height: 1.6;
            color: #6b7a93;
        }

        /* --- Ikon amplop + gembok --- */
        .mail-icon {
            position: relative;
            width: 84px;
            height: 84px;
            margin: 0 auto;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4f8ee0, #3a7ad4);
            box-shadow: 0 14px 30px rgba(58, 122, 212, 0.35);
        }

        .mail-icon .mail {
            font-size: 36px;
            color: #ffffff;
        }

        .mail-icon .lock {
            position: absolute;
            right: -6px;
            bottom: -6px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border: 2px solid #eaf0f8;
            color: #3a7ad4;
            font-size: 13px;
        }

        /* --- Sparkles kecil di sekitar ikon --- */
        .mail-icon .spark {
            position: absolute;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #9cc3f2;
        }

        .mail-icon .s1 { top: -12px; left: 8px; }
        .mail-icon .s2 { top: 6px; right: -16px; width: 5px; height: 5px; }
        .mail-icon .s3 { bottom: -14px; left: 22px; width: 5px; height: 5px; }
        .mail-icon .s4 { top: -6px; right: 2px; width: 4px; height: 4px; }
        .mail-icon .s5 { bottom: 4px; left: -16px; width: 6px; height: 6px; }
        .mail-icon .s6 { top: -18px; right: 26px; width: 4px; height: 4px; }

        /* --- Alert --- */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            text-align: left;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #067647;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        /* --- Form --- */
        .field-label {
            display: block;
            text-align: left;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            margin: 0 0 6px;
        }

        .field {
            position: relative;
            display: flex;
            align-items: center;
            margin-bottom: 6px;
        }

        .field > i {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 15px;
        }

        .field input {
            width: 100%;
            padding: 13px 14px 13px 42px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            font-family: inherit;
            font-size: 14px;
            color: #1a2f4c;
            transition: 0.2s;
        }

        .field input:focus {
            outline: none;
            background: #ffffff;
            border-color: #3a7ad4;
            box-shadow: 0 0 0 3px rgba(58, 122, 212, 0.15);
        }

        .field.has-error input {
            border-color: #fca5a5;
            background: #fff5f5;
        }

        .field.has-eye input { padding-right: 44px; }

        .toggle-eye {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: #94a3b8;
            font-size: 15px;
            cursor: pointer;
            border-radius: 8px;
            transition: 0.2s;
        }

        .toggle-eye:hover { color: #3a7ad4; }

        .field-wrap { margin-bottom: 16px; }

        .field-error {
            display: block;
            text-align: left;
            color: #dc2626;
            font-size: 12px;
            margin: 2px 0 0;
        }

        .btn-submit {
            width: 100%;
            margin-top: 18px;
            padding: 13px 16px;
            border: none;
            border-radius: 12px;
            background: #3a7ad4;
            color: #ffffff;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 10px 22px rgba(58, 122, 212, 0.28);
            transition: 0.2s;
        }

        .btn-submit:hover {
            background: #2d6ac0;
            transform: translateY(-1px);
        }

        .btn-submit:active { transform: translateY(0); }

        .divider {
            border: none;
            border-top: 1px solid #eef2f7;
            margin: 24px 0 18px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13.5px;
            font-weight: 600;
            color: #3a7ad4;
            text-decoration: none;
        }

        .back-link:hover { color: #2457a1; text-decoration: underline; }

        .auth-note {
            margin-top: 18px;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .auth-note strong { color: #3a7ad4; }
    </style>
</head>
<body>
    <div class="auth-backdrop"></div>

    @yield('content')

    @stack('scripts')
</body>
</html>
