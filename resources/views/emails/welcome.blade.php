<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ __('emails.welcome.title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Reset styles */
        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            background-color: #f4f7fa;
            font-family: 'Roboto', sans-serif;
        }

        /* Container */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        /* Header */
        .header {
            background: #FF8400;
            padding: 40px 20px;
            text-align: center;
        }

        .logo {
            max-width: 200px;
            height: auto;
        }

        /* Content */
        .content {
            padding: 40px 30px;
            color: #333333;
            line-height: 1.6;
        }

        .greeting {
            font-size: 28px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            margin-top: 0;
        }

        .text {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 20px;
        }

        /* CTA Button */
        .cta-container {
            text-align: center;
            margin: 35px 0;
        }

        .cta-button {
            display: inline-block;
            padding: 16px 40px;
            background: #FF8400;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 132, 0, 0.4);
        }

        .cta-button:hover {
            box-shadow: 0 6px 20px rgba(255, 132, 0, 0.6);
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            background-color: #2d3748;
            color: #cbd5e0;
            padding: 30px;
            text-align: center;
            font-size: 14px;
        }

        .footer-text {
            margin-bottom: 15px;
            color: #a0aec0;
        }

        .footer-link {
            color: #FF8400;
            text-decoration: none;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        /* Signature */
        .signature {
            margin-top: 30px;
            font-style: italic;
            color: #718096;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px !important;
            }
            .greeting {
                font-size: 24px !important;
            }
            .text {
                font-size: 15px !important;
            }
            .cta-button {
                padding: 14px 30px !important;
                font-size: 15px !important;
            }
        }
    </style>
</head>
<body>
    <!-- Wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 20px 0;">
                <!-- Email Container -->
                <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" width="600" align="center">

                    <!-- Header -->
                    <tr>
                        <td class="header">
                                <img src="{{ asset('plus+white.png') }}" alt="UpPlus+" class="logo">
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <h1 class="greeting">{{ __('emails.welcome.greeting', ['name' => $user->first_name]) }}</h1>

                            <p class="text">
                                {{ __('emails.welcome.intro') }}
                            </p>

                            <p class="text">
                                {{ __('emails.welcome.help') }}
                            </p>

                            <p class="text">
                                {{ __('emails.welcome.access') }}
                            </p>

                            <!-- CTA Button -->
                            <div class="cta-container">
                                <a href="{{ $url }}" class="cta-button">
                                    {{ __('emails.welcome.cta_button') }}
                                </a>
                            </div>

                            <p class="signature">
                                {{ __('emails.welcome.signature') }}<br>
                                <strong>{{ __('emails.welcome.team') }}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p class="footer-text">
                                {{ __('emails.welcome.footer_copyright', ['year' => date('Y')]) }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
