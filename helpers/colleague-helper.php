<?php

declare(strict_types=1);

function colleague_catalog(): array
{
    return [
        'North' => [
            'IBI' => [
                'id' => 'IBI',
                'name' => 'Indore Business Institute',
                'api_endpoint' => 'https://api.nopaperforms.com/dataporting/578/career_mantra',
                'api_token' => '76f848961f95f47d9a9c7ce4f80d41c9',
            ],
            'IILM' => [
                'id' => 'IILM',
                'name' => 'Institute for Integrated Learning in Management',
                'api_endpoint' => 'https://api.nopaperforms.com/dataporting/377/career_mantra',
                'api_token' => '8a44b9c9743ed51af2795dea2f3bc7e4',
            ],
            'GNOIT' => [
                'id' => 'GNOIT',
                'name' => 'Greater Noida Institute of Technology',
                'api_endpoint' => 'https://api.nopaperforms.com/dataporting/19/career_mantra',
                'api_token' => '228a0331060cd6d994497bbed9801f88',
            ],
            'DBUU' => [
                'id' => 'DBUU',
                'name' => 'Dev Bhoomi Uttarakhand University',
                'api_endpoint' => '',
                'api_token' => '',
            ],
            'JKBS' => [
                'id' => 'JKBS',
                'name' => 'JK Business School',
                'api_endpoint' => '',
                'api_token' => '',
            ],
        ],
        'South' => [
            'Sunstone' => [
                'id' => 'Sunstone',
                'name' => 'Sunstone Education',
                'api_endpoint' => 'https://hub-console-api.sunstone.in/lead/leadPush',
                'api_token' => 'cac0713d-076b-4817-b713-af8bc49e5a66',
            ],
            'NITTE' => [
                'id' => 'NITTE',
                'name' => 'Nitte University',
                'api_endpoint' => 'https://api.in5.nopaperforms.com/dataporting/5609/career_mantra',
                'api_token' => '2e27177c6457060315ef5a782df92c16',
            ],
            'KCM' => [
                'id' => 'KCM',
                'name' => 'KCM Bangalore',
                'api_endpoint' => 'https://api.nopaperforms.com/dataporting/434/career_mantra',
                'api_token' => 'ee3289b1bec1041abb2415cea1662b24',
            ],
            'GIBS' => [
                'id' => 'GIBS',
                'name' => 'Global Institute of Business Studies',
                'api_endpoint' => 'https://api.nopaperforms.com/dataporting/374/career_mantra',
                'api_token' => '3eff570317f445677f1c72a4f2c892f2',
            ],
            'Alliance' => [
                'id' => 'Alliance',
                'name' => 'Alliance University',
                'api_endpoint' => 'https://api.nopaperforms.com/dataporting/207/career_mantra',
                'api_token' => '7cd1b1ae7671b9a7ab78e8ece637bf33',
            ],
        ],
        'East' => [],
        'West / Others' => [
            'KKMU' => [
                'id' => 'KKMU',
                'name' => 'K K Modi University',
                'api_endpoint' => 'https://api.nopaperforms.com/dataporting/692/career_mantra',
                'api_token' => 'b5a103f0b5bdfc04851278844c7aa02f',
            ],
            'PPSU' => [
                'id' => 'PPSU',
                'name' => 'P P Savani University',
                'api_endpoint' => 'https://api.in5.nopaperforms.com/dataporting/5562/career_mantra',
                'api_token' => '40a84ffa0a1c0392936d90a998d1c849',
            ],
            'PBS' => [
                'id' => 'PBS',
                'name' => 'Pune Business School',
                'api_endpoint' => 'https://thirdpartyapi.extraaedge.com/api/SaveRequest/',
                'api_token' => 'PCET-19-08-2022',
                'recommended_source' => 'pcet',
                'external_college_id' => '',
            ],
            // College Configuration: PCU College
            // API URL: https://api.in8.nopaperforms.com/dataporting/5674/career_mantra
            // Secret Key: 0ac841e88c5faf0f145087487174cc29
            // Recommended Source: career_mantra
            // College ID: 5674
            // Default Region: Western
            // Note: Use this configuration when pushing leads to PCU College API.
            // Future colleges can be added using the same structure.
            'PCU' => [
                'id' => 'PCU',
                'name' => 'PCU College',
                'api_endpoint' => 'https://api.in8.nopaperforms.com/dataporting/5674/career_mantra',
                'api_token' => '0ac841e88c5faf0f145087487174cc29',
                'recommended_source' => 'career_mantra',
                'external_college_id' => '5674',
            ],
            'Lexicon' => [
                'id' => 'Lexicon',
                'name' => 'Lexicon Management Institute',
                'api_endpoint' => 'https://api.nopaperforms.com/dataporting/375/career_mantra',
                'api_token' => 'e1c2e501cb7b5d4ba5f0eef5a7d350d0',
                'recommended_source' => 'career_mantra',
                'external_college_id' => '375',
            ],
        ],
    ];
}
