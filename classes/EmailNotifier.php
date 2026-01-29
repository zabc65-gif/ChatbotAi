<?php
/**
 * Classe EmailNotifier
 * Envoie les notifications email pour les rendez-vous
 */

class EmailNotifier
{
    /**
     * Envoie une notification de rendez-vous au propriétaire du chatbot
     *
     * @param array $appointment Données du RDV (visitor_name, visitor_email, visitor_phone, service, appointment_date, appointment_time)
     * @param string $notificationEmail Email de destination
     * @param string $chatbotName Nom du chatbot (pour identifier la source)
     * @return bool Succès de l'envoi
     */
    public function sendAppointmentNotification(array $appointment, string $notificationEmail, string $chatbotName = 'Chatbot'): bool
    {
        if (empty($notificationEmail) || !filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = 'Nouveau RDV - ' . ($appointment['visitor_name'] ?? 'Visiteur');

        // Formater la date en français
        $dateFormatted = $this->formatDateFr($appointment['appointment_date']);
        $timeFormatted = $this->formatTime($appointment['appointment_time']);

        $body = $this->buildHtmlEmail($appointment, $dateFormatted, $timeFormatted, $chatbotName);

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ChatBot IA <noreply@chatbot.myziggi.pro>',
            'Reply-To: ' . ($appointment['visitor_email'] ?? 'noreply@chatbot.myziggi.pro'),
            'X-Mailer: ChatBot-IA'
        ];

        return @mail($notificationEmail, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Construit le template HTML de l'email
     */
    private function buildHtmlEmail(array $appointment, string $date, string $time, string $chatbotName): string
    {
        $name = htmlspecialchars($appointment['visitor_name'] ?? 'Non renseigné');
        $email = htmlspecialchars($appointment['visitor_email'] ?? '-');
        $phone = htmlspecialchars($appointment['visitor_phone'] ?? '-');
        $service = htmlspecialchars($appointment['service'] ?? '-');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background: #f1f5f9; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #6366f1; color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 8px 0 0; opacity: 0.8; font-size: 14px; }
        .content { padding: 24px; }
        .field { padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
        .field:last-child { border-bottom: none; }
        .field-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .field-value { font-size: 16px; color: #1e293b; font-weight: 500; }
        .highlight { background: #eff6ff; padding: 16px; border-radius: 8px; margin: 16px 0; text-align: center; }
        .highlight .date { font-size: 20px; font-weight: 700; color: #1d4ed8; }
        .highlight .time { font-size: 16px; color: #3b82f6; margin-top: 4px; }
        .footer { padding: 16px 24px; background: #f8fafc; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouveau Rendez-vous</h1>
            <p>Pris via {$chatbotName}</p>
        </div>
        <div class="content">
            <div class="highlight">
                <div class="date">{$date}</div>
                <div class="time">{$time}</div>
            </div>
            <div class="field">
                <div class="field-label">Nom</div>
                <div class="field-value">{$name}</div>
            </div>
            <div class="field">
                <div class="field-label">Email</div>
                <div class="field-value">{$email}</div>
            </div>
            <div class="field">
                <div class="field-label">Téléphone</div>
                <div class="field-value">{$phone}</div>
            </div>
            <div class="field">
                <div class="field-label">Service demandé</div>
                <div class="field-value">{$service}</div>
            </div>
        </div>
        <div class="footer">
            Envoyé par ChatBot IA - chatbot.myziggi.pro
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Envoie un email de confirmation au visiteur
     *
     * @param array $appointment Données du RDV
     * @param string $businessName Nom de l'entreprise
     * @return bool Succès de l'envoi
     */
    public function sendVisitorConfirmation(array $appointment, string $businessName = 'Notre entreprise'): bool
    {
        $visitorEmail = $appointment['visitor_email'] ?? '';
        if (empty($visitorEmail) || !filter_var($visitorEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = 'Confirmation de votre rendez-vous - ' . $businessName;

        $dateFormatted = $this->formatDateFr($appointment['appointment_date']);
        $timeFormatted = $this->formatTime($appointment['appointment_time']);

        $body = $this->buildVisitorConfirmationEmail($appointment, $dateFormatted, $timeFormatted, $businessName);

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $businessName . ' <noreply@chatbot.myziggi.pro>',
            'X-Mailer: ChatBot-IA'
        ];

        return @mail($visitorEmail, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Construit le template HTML de l'email de confirmation visiteur
     */
    private function buildVisitorConfirmationEmail(array $appointment, string $date, string $time, string $businessName): string
    {
        $name = htmlspecialchars($appointment['visitor_name'] ?? 'Client');
        $service = htmlspecialchars($appointment['service'] ?? '');
        $businessNameHtml = htmlspecialchars($businessName);
        $serviceInfo = $service ? "<p style=\"margin: 8px 0 0; font-size: 14px; color: #64748b;\">Service : {$service}</p>" : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background: #f1f5f9; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 32px 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 8px 0 0; opacity: 0.9; font-size: 14px; }
        .content { padding: 32px 24px; text-align: center; }
        .greeting { font-size: 18px; color: #1e293b; margin-bottom: 24px; }
        .appointment-box { background: #f0fdf4; border: 2px solid #10b981; border-radius: 12px; padding: 24px; margin: 24px 0; }
        .appointment-date { font-size: 22px; font-weight: 700; color: #047857; }
        .appointment-time { font-size: 18px; color: #059669; margin-top: 8px; }
        .info { font-size: 14px; color: #64748b; line-height: 1.6; margin-top: 24px; }
        .footer { padding: 20px 24px; background: #f8fafc; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Rendez-vous confirmé !</h1>
            <p>{$businessNameHtml}</p>
        </div>
        <div class="content">
            <p class="greeting">Bonjour <strong>{$name}</strong>,</p>
            <p style="color: #475569;">Votre rendez-vous a bien été enregistré.</p>

            <div class="appointment-box">
                <div class="appointment-date">{$date}</div>
                <div class="appointment-time">à {$time}</div>
                {$serviceInfo}
            </div>

            <p class="info">
                Si vous avez besoin de modifier ou annuler ce rendez-vous,<br>
                veuillez nous contacter directement.
            </p>
        </div>
        <div class="footer">
            Cet email a été envoyé automatiquement suite à votre prise de rendez-vous.
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Formate une date en français
     */
    private function formatDateFr(string $date): string
    {
        $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        $timestamp = strtotime($date);
        $jourSemaine = $jours[(int)date('w', $timestamp)];
        $jour = (int)date('d', $timestamp);
        $moisNum = (int)date('m', $timestamp);
        $annee = date('Y', $timestamp);

        return "$jourSemaine $jour $mois[$moisNum] $annee";
    }

    /**
     * Formate l'heure
     */
    private function formatTime(string $time): string
    {
        $parts = explode(':', $time);
        return $parts[0] . 'h' . $parts[1];
    }
}
