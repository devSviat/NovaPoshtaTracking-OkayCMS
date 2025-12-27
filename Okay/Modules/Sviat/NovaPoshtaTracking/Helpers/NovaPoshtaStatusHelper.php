<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

/**
 * Хелпер для роботи зі статусами Nova Poshta
 */
class NovaPoshtaStatusHelper
{
    /**
     * Отримує текст статусу за кодом
     * 
     * @param string $statusCode
     * @return string
     */
    public function getStatusText($statusCode)
    {
        $statuses = [
            '1' => 'Створено',
            '2' => 'Видалено',
            '3' => 'Не знайдено',
            '4' => 'У місті відправлення',
            '41' => 'У місті відправлення',
            '5' => 'Прямує до міста',
            '6' => 'У місті отримання',
            '7' => 'Прибуло до відділення',
            '8' => 'У поштоматі',
            '9' => 'Отримано',
            '10' => 'Отримано (очікується переказ)',
            '11' => 'Отримано (переказ видано)',
            '12' => 'Комплектується',
            '101' => 'На шляху до одержувача',
            '102' => 'Відмова (створено повернення)',
            '103' => 'Відмова від отримання',
            '104' => 'Змінено адресу',
            '105' => 'Припинено зберігання',
            '106' => 'Створено ЄН повернення',
            '111' => 'Невдала доставка',
            '112' => 'Дата перенесена',
        ];

        return $statuses[$statusCode] ?? "Статус: {$statusCode}";
    }

    /**
     * Отримує CSS клас баджа за кодом статусу
     * 
     * @param string $statusCode
     * @return string
     */
    public function getBadgeClass($statusCode)
    {
        // Мапінг статусів до класів баджів
        $statusMap = [
            '1' => 'np-status-badge--created',
            '2' => 'np-status-badge--deleted',
            '3' => 'np-status-badge--not-found',
            '4' => 'np-status-badge--in-transit',
            '41' => 'np-status-badge--in-transit',
            '5' => 'np-status-badge--in-transit',
            '6' => 'np-status-badge--in-transit',
            '7' => 'np-status-badge--arrived',
            '8' => 'np-status-badge--arrived',
            '9' => 'np-status-badge--received',
            '10' => 'np-status-badge--received',
            '11' => 'np-status-badge--received',
            '12' => 'np-status-badge--processing',
            '101' => 'np-status-badge--in-transit',
            '102' => 'np-status-badge--refused',
            '103' => 'np-status-badge--refused',
            '104' => 'np-status-badge--changed',
            '105' => 'np-status-badge--changed',
            '106' => 'np-status-badge--return',
            '111' => 'np-status-badge--changed',
            '112' => 'np-status-badge--changed',
        ];

        return $statusMap[$statusCode] ?? 'np-status-badge--failed';
    }

    /**
     * Форматує дані статусу для відображення в шаблоні
     * 
     * @param string|null $statusCode
     * @return array|null
     */
    public function formatStatusForDisplay($statusCode)
    {
        if (empty($statusCode)) {
            return null;
        }

        return [
            'code' => $statusCode,
            'text' => $this->getStatusText($statusCode),
            'badge_class' => $this->getBadgeClass($statusCode),
        ];
    }
}
