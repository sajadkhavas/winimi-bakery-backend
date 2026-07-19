# Composer dependency resolution diagnostics

```text
Loading composer repositories with package information
Updating dependencies
Your requirements could not be resolved to an installable set of packages.

  Problem 1
    - Root composer.json requires laravel/telescope ^5.20 -> satisfiable by laravel/telescope[v5.20.0].
    - Root composer.json requires tapp/filament-authentication-log ^3.1 -> satisfiable by tapp/filament-authentication-log[v3.1.0, v3.1.1, v3.1.2, v3.1.3].
    - laravel/telescope v5.20.0 requires laravel/framework ^8.37|^9.0|^10.0|^11.0|^12.0|^13.0 -> satisfiable by laravel/framework[v12.61.1, v12.62.0, v12.63.0, v12.64.0].
    - rappasoft/laravel-authentication-log v4.0.0 requires illuminate/contracts ^10.0|^11.0 -> satisfiable by illuminate/contracts[v10.0.0, ..., v10.49.0, v11.0.0, ..., v11.51.0].
    - tapp/filament-authentication-log[v3.1.0, ..., v3.1.3] require rappasoft/laravel-authentication-log ^4.0 -> satisfiable by rappasoft/laravel-authentication-log[v4.0.0].
    - Conclusion: don't install laravel/framework v12.62.0 (conflict analysis result)
    - Conclusion: don't install laravel/framework v12.63.0 (conflict analysis result)
    - Conclusion: don't install laravel/framework v12.64.0 (conflict analysis result)
    - Only one of these can be installed: illuminate/contracts[v5.5.0, ..., v5.8.36, v6.0.0, ..., v6.20.44, v7.0.0, ..., v7.30.6, v8.0.0, ..., v8.83.27, v9.0.0, ..., v9.52.16, v10.0.0, ..., v10.49.0, v11.0.0, ..., v11.51.0, v12.0.0, ..., v12.64.0, v13.0.0, ..., v13.20.0], laravel/framework[v12.61.1, v12.62.0, v12.63.0, v12.64.0]. laravel/framework replaces illuminate/contracts and thus cannot coexist with it.

Use the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.
::error ::Your requirements could not be resolved to an installable set of packages.%0A%0A  Problem 1%0A    - Root composer.json requires laravel/telescope ^5.20 -> satisfiable by laravel/telescope[v5.20.0].%0A    - Root composer.json requires tapp/filament-authentication-log ^3.1 -> satisfiable by tapp/filament-authentication-log[v3.1.0, v3.1.1, v3.1.2, v3.1.3].%0A    - laravel/telescope v5.20.0 requires laravel/framework ^8.37|^9.0|^10.0|^11.0|^12.0|^13.0 -> satisfiable by laravel/framework[v12.61.1, v12.62.0, v12.63.0, v12.64.0].%0A    - rappasoft/laravel-authentication-log v4.0.0 requires illuminate/contracts ^10.0|^11.0 -> satisfiable by illuminate/contracts[v10.0.0, ..., v10.49.0, v11.0.0, ..., v11.51.0].%0A    - tapp/filament-authentication-log[v3.1.0, ..., v3.1.3] require rappasoft/laravel-authentication-log ^4.0 -> satisfiable by rappasoft/laravel-authentication-log[v4.0.0].%0A    - Conclusion: don't install laravel/framework v12.62.0 (conflict analysis result)%0A    - Conclusion: don't install laravel/framework v12.63.0 (conflict analysis result)%0A    - Conclusion: don't install laravel/framework v12.64.0 (conflict analysis result)%0A    - Only one of these can be installed: illuminate/contracts[v5.5.0, ..., v5.8.36, v6.0.0, ..., v6.20.44, v7.0.0, ..., v7.30.6, v8.0.0, ..., v8.83.27, v9.0.0, ..., v9.52.16, v10.0.0, ..., v10.49.0, v11.0.0, ..., v11.51.0, v12.0.0, ..., v12.64.0, v13.0.0, ..., v13.20.0], laravel/framework[v12.61.1, v12.62.0, v12.63.0, v12.64.0]. laravel/framework replaces illuminate/contracts and thus cannot coexist with it.%0A%0AUse the option --with-all-dependencies (-W) to allow upgrades, downgrades and removals for packages currently locked to specific versions.
```
