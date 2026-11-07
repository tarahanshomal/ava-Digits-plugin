# Third-party components

The full edition is assembled from the user-supplied IPPanel Digits 8.6.2 distribution. Digits is developed by UnitedOver; the reference distribution identifies IPPanel as its distributor. The source ZIP URL and SHA256 are pinned in `scripts/package_full.py`. Original source and dependency notices are retained. This is an Ava adaptation, not an official UnitedOver or IPPanel release. The Ava adapter's GPL license does not replace the separate terms of the supplied core and its dependencies.

The following JavaScript dependencies are distributed locally with their original MIT license texts under `assets/vendor/`. The full edition uses these exact files instead of the reference distribution's remote CDN scripts:

| Component | Version | License | Source |
| --- | --- | --- | --- |
| ApexCharts | 3.54.1 | MIT | https://registry.npmjs.org/apexcharts/3.54.1 |
| jQuery Mask Plugin | 1.14.16 | MIT | https://registry.npmjs.org/jquery-mask-plugin/1.14.16 |
| libphonenumber-js | 1.11.20 | MIT | https://registry.npmjs.org/libphonenumber-js/1.11.20 |

The standalone adapter does not load these dependencies; the full-edition bootstrap maps the original Digits script handles to local copies. A small local stylesheet supplies fallback typography instead of requesting Google Fonts.
