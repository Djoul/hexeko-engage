<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ __('emails.cognito.reset_password.title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;600;700&display=swap" rel="stylesheet">
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
            padding: 30px 20px;
            text-align: center;
        }

        .logo {
            max-width: 180px;
            height: auto;
        }

        /* Content */
        .content {
            padding: 40px 30px;
            color: #333333;
            line-height: 1.6;
        }

        .heading {
            font-size: 24px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            margin-top: 0;
            text-align: center;
        }

        .text {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Code Box */
        .code-container {
            text-align: center;
            margin: 35px 0;
            padding: 30px;
            background-color: #f8fafc;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }

        .code-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .code {
            font-size: 36px;
            font-weight: 700;
            color: #FF8400;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }

        .expiry-note {
            font-size: 14px;
            color: #718096;
            margin-top: 15px;
        }

        /* Warning Box */
        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .warning-text {
            font-size: 14px;
            color: #856404;
            margin: 0;
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
            margin-bottom: 10px;
            color: #a0aec0;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px !important;
            }
            .heading {
                font-size: 20px !important;
            }
            .text {
                font-size: 15px !important;
            }
            .code {
                font-size: 28px !important;
                letter-spacing: 6px !important;
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
                            <h1 class="heading">{{ __('emails.cognito.reset_password.heading') }}</h1>

                            <p class="text">
                                {{ __('emails.cognito.reset_password.intro') }}
                            </p>

                            <!-- Code Box -->
                            <div class="code-container">
                                <div class="code-label">{{ __('emails.cognito.reset_password.code_label') }}</div>
                                <div class="code">{{ $code }}</div>
                                <div class="expiry-note">{{ __('emails.cognito.reset_password.expiry') }}</div>
                            </div>

                            <p class="text">
                                {{ __('emails.cognito.reset_password.instructions') }}
                            </p>

                            <!-- Warning Box -->
                            <div class="warning-box">
                                <p class="warning-text">
                                    <strong>⚠️ {{ __('emails.cognito.reset_password.warning_title') }}</strong><br>
                                    {{ __('emails.cognito.reset_password.warning_text') }}
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p class="footer-text">
                                {{ __('emails.cognito.reset_password.footer_copyright', ['year' => date('Y')]) }}
                            </p>
                            <p class="footer-text">
                                {{ __('emails.cognito.reset_password.footer_support') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
