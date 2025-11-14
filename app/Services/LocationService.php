<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class LocationService
{
    /**
     * Get all available countries
     */
    public function getCountries(): array
    {
        $countriesFile = resource_path('data/countries.php');

        if (!File::exists($countriesFile)) {
            return [];
        }

        $countries = require $countriesFile;

        // Convert to key-value pairs for select options
        return array_combine($countries, $countries);
    }

    /**
     * Get all states/counties for a specific country
     *
     * @param string $countryCode Country code (e.g., 'ro', 'us')
     */
    public function getStates(string $countryCode): array
    {
        $countryCode = strtolower($countryCode);
        $statesFile = resource_path("data/{$countryCode}/counties.php");

        if (!File::exists($statesFile)) {
            return [];
        }

        $states = require $statesFile;

        // Convert to key-value pairs for select options
        return array_combine($states, $states);
    }

    /**
     * Get all cities for a specific country and state
     *
     * @param string $countryCode Country code (e.g., 'ro', 'us')
     * @param string $state State/county name
     */
    public function getCities(string $countryCode, string $state): array
    {
        $countryCode = strtolower($countryCode);

        // Replace spaces with underscores for file names
        $stateFileName = str_replace(' ', '_', $state);
        $citiesFile = resource_path("data/{$countryCode}/cities/{$state}.php");

        // Try with underscores if original doesn't exist
        if (!File::exists($citiesFile)) {
            $citiesFile = resource_path("data/{$countryCode}/cities/{$stateFileName}.php");
        }

        if (!File::exists($citiesFile)) {
            return [];
        }

        $cities = require $citiesFile;

        // Convert to key-value pairs for select options
        // Use city name as both key and value
        return collect($cities)->pluck('name', 'name')->toArray();
    }

    /**
     * Get city details including coordinates
     *
     * @param string $countryCode Country code (e.g., 'ro', 'us')
     * @param string $state State/county name
     * @param string $cityName City name to find
     */
    public function getCityDetails(string $countryCode, string $state, string $cityName): ?array
    {
        $countryCode = strtolower($countryCode);
        $stateFileName = str_replace(' ', '_', $state);
        $citiesFile = resource_path("data/{$countryCode}/cities/{$state}.php");

        // Try with underscores if original doesn't exist
        if (!File::exists($citiesFile)) {
            $citiesFile = resource_path("data/{$countryCode}/cities/{$stateFileName}.php");
        }

        if (!File::exists($citiesFile)) {
            return null;
        }

        $cities = require $citiesFile;

        // Find the city by name
        foreach ($cities as $city) {
            if ($city['name'] === $cityName) {
                return $city;
            }
        }

        return null;
    }

    /**
     * Check if a country has location data available
     */
    public function hasCountryData(string $countryCode): bool
    {
        $countryCode = strtolower($countryCode);
        return File::exists(resource_path("data/{$countryCode}"));
    }

    /**
     * Get country code from country name
     * For now, we'll use a simple mapping for Romania
     * This can be extended later
     */
    public function getCountryCode(string $countryName): ?string
    {
        $mapping = [
            'Romania' => 'ro',
            'United States' => 'us',
            // Add more mappings as needed
        ];

        return $mapping[$countryName] ?? null;
    }
}
