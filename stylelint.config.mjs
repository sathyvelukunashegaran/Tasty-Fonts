const customPropertyPattern = '^tasty-[a-z0-9]+(?:-[a-z0-9]+)*$';
const tastyClassPattern = [
  '^(?:tasty-fonts|dashicons|is-|has-|screen-reader-text|button|button-primary|button-secondary|button-small|regular-text|hidden)(?:[a-z0-9_-]|--)*$',
  {
    message: 'Expected plugin CSS classes to use the tasty-fonts-* namespace or an approved WordPress/state class.'
  }
];

/** @type {import('stylelint').Config} */
export default {
  extends: ['stylelint-config-standard'],
  ignoreFiles: [
    'vendor/**',
    'node_modules/**',
    'output/**',
    'tmp/**',
    'dist/**',
    'build/**'
  ],
  rules: {
    'alpha-value-notation': 'number',
    'color-function-alias-notation': null,
    'color-function-notation': 'legacy',
    'color-hex-length': null,
    'comment-empty-line-before': null,
    'custom-property-pattern': [
      customPropertyPattern,
      {
        message: 'Expected custom properties to use --tasty-* kebab-case names.'
      }
    ],
    'custom-property-empty-line-before': null,
    'declaration-block-no-redundant-longhand-properties': null,
    'declaration-empty-line-before': null,
    'declaration-property-value-keyword-no-deprecated': null,
    'declaration-property-value-no-unknown': [
      true,
      {
        ignoreProperties: {
          '/.+/': [
            '/var\\(--/',
            '/env\\(/',
            '/-apple-system/',
            '/BlinkMacSystemFont/',
            '/ui-monospace/'
          ]
        }
      }
    ],
    'font-family-name-quotes': 'always-where-recommended',
    'function-url-quotes': 'always',
    'media-feature-range-notation': 'prefix',
    'no-descending-specificity': null,
    'no-duplicate-selectors': null,
    'property-no-deprecated': null,
    'property-no-vendor-prefix': null,
    'rule-empty-line-before': null,
    'selector-class-pattern': tastyClassPattern,
    'selector-id-pattern': null,
    'selector-not-notation': 'simple',
    'selector-pseudo-class-no-unknown': [
      true,
      {
        ignorePseudoClasses: ['global']
      }
    ],
    'value-keyword-case': null
  },
  overrides: [
    {
      files: ['assets/css/admin.css'],
      rules: {
        'color-no-hex': [
          true,
          {
            message: 'Prefer tokens from assets/css/tokens.css instead of raw hex values in admin.css.'
          }
        ]
      }
    },
    {
      files: ['assets/css/tokens.css'],
      rules: {
        'color-no-hex': null
      }
    },
    {
      files: ['assets/css/admin-rtl.css'],
      rules: {
        'color-no-hex': true
      }
    }
  ]
};
