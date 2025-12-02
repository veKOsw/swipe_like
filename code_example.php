<?php

namespace Test\SEO;

use Bitrix\Iblock\InheritedProperty\SectionTemplates;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class Management
{
    public const PROVIDERS = ['ChatGPT', 'DeepSeek'];
    public const AI_MODELS = [
        'ChatGPT' => [
            "GPT-4o mini", "GPT-4o", "GPT-4.1", "GPT-4.1 mini", "GPT-4.1 nano", "o4 mini", "o3", "o3 mini", "GPT-4.5"
        ],
        'DeepSeek' => [
            "DeepSeek-V3", "DeepSeek-R1"
        ],
    ];

    public function __construct()
    {
        $this->seoTable = new SEOTable();
        $this->metaHistoryTable = new MetaHistoryTable();
    }

    public function getDefaultValues(): array
    {
        return [
            'API_KEY' => '',
            'PROVIDER' => 'ChatGPT',
            'AI_MODEL' => 'GPT-4o mini',
            'COMPANY_NAME' => '',
            'PRIMARY_BENEFIT' => '',
            'SECONDARY_BENEFIT' => '',
            'LOCATION' => '',
            'LANGUAGE' => 'Русский',
            'GENERATE_TITLE' => 'Y',
            'GENERATE_DESCRIPTION' => 'Y',
            'GENERATE_H1' => 'Y',
            'LIMIT' => '100',
            'SKIP_PROCESSED' => 'Y',
            'EXCLUSIONS' => '',
            'EXCLUDE_WITH_CHILDREN' => 'N',
            'CONSIDER_PARENT_CATEGORIES' => 'Y',
            'MAX_LENGTH_TITLE' => 80,
            'MAX_LENGTH_DESCRIPTION' => 255,
            'MAX_LENGTH_H1' => 70,
            'MAX_LENGTH_CATEGORY_DESCRIPTION' => 1200,
            'TEST_CATEGORY_ID' => 0,
            'IBLOCK_ID' => 0,
            'SECTION_ID' => 0,
        ];
    }

    public static function sanitizeCheckbox(string $value): string
    {
        return $value === 'Y' ? 'Y' : 'N';
    }

    public function getFormDataFromPost(array $post): array
    {
        return [
            'API_KEY' => trim($post['API_KEY'] ?? ''),
            'PROVIDER' => $post['PROVIDER'] ?? '',
            'AI_MODEL' => $post['AI_MODEL'] ?? '',
            'COMPANY_NAME' => trim($post['COMPANY_NAME'] ?? ''),
            'PRIMARY_BENEFIT' => trim($post['PRIMARY_BENEFIT'] ?? ''),
            'SECONDARY_BENEFIT' => trim($post['SECONDARY_BENEFIT'] ?? ''),
            'LOCATION' => trim($post['LOCATION'] ?? ''),
            'LANGUAGE' => trim($post['LANGUAGE'] ?? ''),
            'GENERATE_TITLE' => self::sanitizeCheckbox($post['GENERATE_TITLE'] ?? 'N'),
            'GENERATE_DESCRIPTION' => self::sanitizeCheckbox($post['GENERATE_DESCRIPTION'] ?? 'N'),
            'GENERATE_H1' => self::sanitizeCheckbox($post['GENERATE_H1'] ?? 'N'),
            'GENERATE_CATEGORY_DESCRIPTION' => self::sanitizeCheckbox($post['GENERATE_CATEGORY_DESCRIPTION'] ?? 'N'),
            'LIMIT' => $post['LIMIT'] ?? 'Все',
            'SKIP_PROCESSED' => self::sanitizeCheckbox($post['SKIP_PROCESSED'] ?? 'N'),
            'EXCLUSIONS' => trim($post['EXCLUSIONS'] ?? ''),
            'EXCLUDE_WITH_CHILDREN' => self::sanitizeCheckbox($post['EXCLUDE_WITH_CHILDREN'] ?? 'N'),
            'CONSIDER_PARENT_CATEGORIES' => self::sanitizeCheckbox($post['CONSIDER_PARENT_CATEGORIES'] ?? 'N'),
            'MAX_LENGTH_TITLE' => (int)($post['MAX_LENGTH_TITLE'] ?? 0),
            'MAX_LENGTH_DESCRIPTION' => (int)($post['MAX_LENGTH_DESCRIPTION'] ?? 0),
            'MAX_LENGTH_H1' => (int)($post['MAX_LENGTH_H1'] ?? 0),
            'MAX_LENGTH_CATEGORY_DESCRIPTION' => (int)($post['MAX_LENGTH_CATEGORY_DESCRIPTION'] ?? 0),
            'TEST_CATEGORY_ID' => (int)($post['TEST_CATEGORY_ID'] ?? 0),
            'IBLOCK_ID' => (int)($post['IBLOCK_ID'] ?? 0),
            'SECTION_ID' => (int)($post['SECTION_ID'] ?? 0),
        ];
    }

    public function validateForm(array $data): array
    {
        $errors = [];
        if (trim($data['API_KEY']) === '') {
            $errors[] = Loc::getMessage('ERROR_API_KEY_REQUIRED', ['#FIELD#' => 'Ключ API']);
        }
        if (trim($data['LANGUAGE']) === '') {
            $errors[] = Loc::getMessage('ERROR_LANGUAGE_REQUIRED', ['#FIELD#' => 'Язык']);
        }
        if (!in_array($data['PROVIDER'], self::PROVIDERS, true)) {
            $errors[] = Loc::getMessage('ERROR_INVALID_PROVIDER');
        }
        if (!in_array($data['AI_MODEL'], self::AI_MODELS[$data['PROVIDER']] ?? [], true)) {
            $errors[] = Loc::getMessage('ERROR_INVALID_AI_MODEL');
        }
        if ($data['LIMIT'] !== 'Все' && !ctype_digit((string)$data['LIMIT'])) {
            $errors[] = Loc::getMessage('ERROR_INVALID_LIMIT');
        }
        $intFields = ['MAX_LENGTH_TITLE', 'MAX_LENGTH_DESCRIPTION', 'MAX_LENGTH_H1', 'MAX_LENGTH_CATEGORY_DESCRIPTION', 'TEST_CATEGORY_ID'];
        foreach ($intFields as $field) {
            if (!is_numeric($data[$field]) || (int)$data[$field] < 0) {
                $errors[] = Loc::getMessage('ERROR_INVALID_FIELD', ['#FIELD#' => $field]);
            }
        }
        return $errors;
    }

    private function normalizeRecord(array $data): array
    {
        $data['MAX_LENGTH_TITLE'] = (int)$data['MAX_LENGTH_TITLE'];
        $data['MAX_LENGTH_DESCRIPTION'] = (int)$data['MAX_LENGTH_DESCRIPTION'];
        $data['MAX_LENGTH_H1'] = (int)$data['MAX_LENGTH_H1'];
        $data['MAX_LENGTH_CATEGORY_DESCRIPTION'] = (int)$data['MAX_LENGTH_CATEGORY_DESCRIPTION'];
        $data['TEST_CATEGORY_ID'] = (int)$data['TEST_CATEGORY_ID'];

        $checkboxFields = ['GENERATE_TITLE', 'GENERATE_DESCRIPTION', 'GENERATE_H1', 'GENERATE_CATEGORY_DESCRIPTION', 'SKIP_PROCESSED', 'EXCLUDE_WITH_CHILDREN', 'CONSIDER_PARENT_CATEGORIES'];
        foreach ($checkboxFields as $field) {
            $data[$field] = self::sanitizeCheckbox($data[$field] ?? 'N');
        }
        return $data;
    }

    public function getIblocks(): array
    {
        $iblocks = [];
        $res = \CIBlock::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], ['ACTIVE' => 'Y']);
        while ($iblock = $res->Fetch()) {
            $iblocks[$iblock['ID']] = $iblock['NAME'];
        }
        return $iblocks;
    }

    public function getSections(int $iblockId): array
    {
        $sections = [];
        if ($iblockId <= 0) {
            return $sections;
        }
        $res = \CIBlockSection::GetList(
            ['LEFT_MARGIN' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME', 'DEPTH_LEVEL']
        );
        while ($section = $res->Fetch()) {
            $sections[$section['ID']] = str_repeat('— ', $section['DEPTH_LEVEL'] - 1) . $section['NAME'];
        }
        return $sections;
    }

    public function getSectionsFull($parameters)
    {
        $arSections = [];
        $filter = [
            'IBLOCK_ID' => $parameters['IBLOCK_ID'],
            'ACTIVE' => 'Y',
        ];
        if (!empty($parameters['SECTION_ID']) && $parameters['SECTION_ID'] > 0) {
            $parentSection = \CIBlockSection::GetByID($parameters['SECTION_ID'])->Fetch();
            if ($parentSection) {
                $filter['>LEFT_MARGIN'] = $parentSection['LEFT_MARGIN'];
                $filter['<RIGHT_MARGIN'] = $parentSection['RIGHT_MARGIN'];
                if (isset($parameters['CONSIDER_PARENT_CATEGORIES']) && $parameters['CONSIDER_PARENT_CATEGORIES'] === 'N') {
                    $filter['!ID'] = [$parameters['SECTION_ID']];
                }
            }
        }
        $excludedIds = [];
        if (isset($parameters['EXCLUSIONS']) && !empty($parameters['EXCLUSIONS'])) {
            $excludedIds = array_map('trim', explode(',', $parameters['EXCLUSIONS']));
            $excludedIds = array_filter($excludedIds, 'is_numeric');
            $excludedIds = array_map('intval', $excludedIds);
        }
        if (!empty($parameters['EXCLUDE_WITH_CHILDREN']) && $parameters['EXCLUDE_WITH_CHILDREN'] === 'Y') {
            foreach ($excludedIds as $id) {
                $excludedIds = array_merge($excludedIds, $this->getChildSectionsRecursive($id));
            }
        }
        $excludedIds = array_unique($excludedIds);
        if (isset($filter['!ID'])) {
            if (!is_array($filter['!ID'])) {
                $filter['!ID'] = [$filter['!ID']];
            }
            $filter['!ID'] = array_merge($filter['!ID'], $excludedIds);
            $filter['!ID'] = array_unique($filter['!ID']);
        } elseif (!empty($excludedIds)) {
            $filter['!ID'] = $excludedIds;
        }
        $arNavParams = [];
        if (isset($parameters['LIMIT']) && (int)$parameters['LIMIT'] > 0) {
            $arNavParams['nTopCount'] = (int)$parameters['LIMIT'];
        }
        $sections = \CIBlockSection::GetList(
            ['LEFT_MARGIN' => 'ASC'],
            $filter,
            false,
            ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'UF_AUTO_GENERATED'],
            $arNavParams
        );
        while ($section = $sections->Fetch()) {
            if (!in_array((int)$section['ID'], $excludedIds, true)) {
                $arSections[$section['ID']] = $section;
            }
        }
        return $arSections;
    }

    public function getChildSectionsRecursive($id)
    {
        $children = [];
        $rsSections = SectionTable::getList([
            'filter' => ['=IBLOCK_SECTION_ID' => $id],
            'select' => ['ID'],
        ]);
        while ($section = $rsSections->fetch()) {
            $childId = (int)$section['ID'];
            $children[] = $childId;
            $children = array_merge($children, $this->getChildSectionsRecursive($childId));
        }
        return $children;
    }

    public function getParentChain(int $sectionId, array $sections, int $levels = 2): array
    {
        $chain = [];
        $currentId = $sectionId;
        for ($i = 0; $i < $levels; $i++) {
            if (!isset($sections[$currentId]) || empty($sections[$currentId]['IBLOCK_SECTION_ID'])) {
                break;
            }
            $parentId = $sections[$currentId]['IBLOCK_SECTION_ID'];
            if ($parentId == 0) {
                break;
            }
            if (!isset($sections[$parentId])) {
                break;
            }
            $chain[] = $sections[$parentId]['NAME'];
            $currentId = $parentId;
        }
        return $chain;
    }

    public function saveOldSEO($section, $iblockID)
    {
        $ipropSectionTemplates = new SectionTemplates($iblockID, $section);
        $templates = $ipropSectionTemplates->findTemplates();

// Определяем значения по умолчанию
        $sectionName        = '';
        $sectionTitle       = '';
        $sectionDescription = '';

// Если в массиве templates есть нужные ключи – берём их, иначе остаётся ''
        if (!empty($templates['SECTION_PAGE_TITLE']['TEMPLATE'])) {
            $sectionName = $templates['SECTION_PAGE_TITLE']['TEMPLATE'];
        }
        if (!empty($templates['SECTION_META_TITLE']['TEMPLATE'])) {
            $sectionTitle = $templates['SECTION_META_TITLE']['TEMPLATE'];
        }
        if (!empty($templates['SECTION_META_DESCRIPTION']['TEMPLATE'])) {
            $sectionDescription = $templates['SECTION_META_DESCRIPTION']['TEMPLATE'];
        }

// Всегда создаём запись
        $this->metaHistoryTable::add([
            "ID_SECTION"          => $section,
            "SEO_ID"              => $this->seoTable->loadLastRecord()['ID'],
            "SECTION_NAME"        => $sectionName,
            "SECTION_TITLE"       => $sectionTitle,
            "SECTION_DESCRIPTION" => $sectionDescription,
        ]);
    }

    public function runGeneration($sections, $parameters)
    {
        $parameters[] = [
            "API_KEY" => $parameters['API_KEY'],
            "AI_MODEL" => $parameters['AI_MODEL'],
            "COMPANY_NAME" => $parameters['COMPANY_NAME'],
            "PRIMARY_BENEFIT" => $parameters['PRIMARY_BENEFIT'],
            "SECONDARY_BENEFIT" => $parameters['SECONDARY_BENEFIT'],
            "LOCATION" => $parameters['LOCATION'],
            "LANGUAGE" => $parameters['LANGUAGE'],
            "MAX_LENGTH_TITLE" => $parameters['MAX_LENGTH_TITLE'],
            "MAX_LENGTH_H1" => $parameters['MAX_LENGTH_H1'],
            "MAX_LENGTH_DESCRIPTION" => $parameters['MAX_LENGTH_DESCRIPTION'],
        ];
        $dataSection = [];
        foreach ($sections as $section) {
            $this->saveOldSEO($section['ID'], $parameters['IBLOCK_ID']);

            if ((int)$section['UF_AUTO_GENERATED'] === 1 && $parameters['SKIP_PROCESSED'] === 'Y') {
                continue;
            }
            $parents = $this->getParentChain($section['ID'], $sections, 2);
            $dataSection[] = [
                "category_id" => $section['ID'],
                "category_name" => $section['NAME'],
                "parent_category_1" => $parents[0] ?? "",
                "parent_category_2" => $parents[1] ?? ""
            ];
        }
        $dataApi = [
            'data1' => $parameters,
            'data2' => $dataSection
        ];
        $jsonData = json_encode($dataApi);
        $url = 'https://test/seo/';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Ошибка cURL: ' . curl_error($ch);
            curl_close($ch);
            exit;
        }
        curl_close($ch);
        $responseFile = __DIR__ . '/seo_response.json';
        file_put_contents($responseFile, $response);
        $responseData = json_decode(file_get_contents($responseFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Ошибка декодирования JSON из файла: " . json_last_error_msg();
            exit;
        }
        if (!is_array($responseData)) {
            echo "Неверный формат ответа в файле";
            exit;
        }
        $bs = new \CIBlockSection();
        foreach ($responseData as $item) {
            $sectionId = $item['id'] ?? null;
            if (!$sectionId || !isset($sections[$sectionId])) {
                continue;
            }
            $fieldsToUpdate = [
                'UF_H1' => $item['h1'] ?? null,
                'UF_DESCRIPTION' => $item['meta_description'] ?? null,
                'UF_TITLE' => $item['title'] ?? null,
                'UF_AUTO_GENERATED' => 1
            ];
            $fieldsToUpdate = array_filter($fieldsToUpdate, function ($value) {
                return $value !== null;
            });
            if (!empty($fieldsToUpdate)) {
                $res = $bs->Update($sectionId, $fieldsToUpdate);
                if (!$res) {
                    echo "Ошибка обновления раздела ID {$sectionId}: " . $bs->LAST_ERROR . "<br>";
                } else {
                    echo "Раздел ID {$sectionId} успешно обновлен.<br>";
                }
            }
        }
        $this->seoTable::update($this->seoTable->loadLastRecord()['ID'], [
            'DONE' => 'Y'
        ]);

    }

}
