/* eslint-disable import/no-nodejs-modules */
const webpack = require( 'webpack' );
const path = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const autoprefixer = require( 'autoprefixer' );
const customProperties = require( 'postcss-custom-properties' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const os = require( 'os' );
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
const MomentTimezoneDataPlugin = require( 'moment-timezone-data-webpack-plugin' );
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');
const safePostCssParser = require('postcss-safe-parser');
const { webpackAlias: coreE2EAlias } = require( '@woocommerce/e2e-environment' );

const isProd = 'production' === process.env.NODE_ENV;
const isI18n = 'i18n' === process.env.NODE_ENV;
const isDev = ! isProd && ! isI18n;

const browsers = 'last 2 versions, not ie_mob 10, not ie 10';

const cssLoaders = [
	isDev ? 'style-loader' : MiniCssExtractPlugin.loader,
	'css-loader',
	{
		loader: 'postcss-loader',
		options: {
			plugins: () => [
				autoprefixer( { browsers } ),
				customProperties( { preserve: false } ),
			],
		},
	},
	{
		loader: 'sass-loader',
		options: {
			includePaths: [
				path.resolve( __dirname, 'client' ),
				path.resolve( __dirname, 'client', 'extensions' ),
				path.resolve( __dirname, 'assets', 'stylesheets' ),
				path.resolve( __dirname, 'wp-calypso', 'client' ),
				path.resolve( __dirname, 'wp-calypso', 'assets', 'stylesheets' ),
			],
		},
	},
];

module.exports = {
	bail: ! isDev,
	context: __dirname,
	mode: isDev ? 'development' : 'production',
	devtool: isDev ? 'inline-source-map' : false,
	cache: true,
	entry: {
		'woocommerce-services': [ './client/main.js' ],
		'woocommerce-services-banner': [ './client/banner.js' ],
		'woocommerce-services-admin-pointers': [ './client/admin-pointers.js' ],
		'woocommerce-services-new-order-taxjar': [ './client/new-order-taxjar.js' ],
	},
	output: Object.assign(
			{},
			{
			path: path.join( __dirname, 'dist' ),
			filename: '[name]-' + process.env.npm_package_version + '.js',
			chunkFilename: 'chunks/[chunkhash].min.js',
			devtoolModuleFilenameTemplate: 'app:///[resource-path]',
		},
		isDev ? {
			publicPath: 'http://localhost:8085/'
		} : {}
	),
	optimization: {
		minimize: ! isDev,
		minimizer: [
			new TerserPlugin( {
				cache: true,
				parallel: true,
				terserOptions: {
					parse: {
		              // We want terser to parse ecma 8 code. However, we don't want it
		              // to apply any minification steps that turns valid ecma 5 code
		              // into invalid ecma 5 code. This is why the 'compress' and 'output'
		              // sections only apply transformations that are ecma 5 safe
		              // https://github.com/facebook/create-react-app/pull/4234
		              ecma: 8,
		            },
					compress: {
		              ecma: 5,
		              warnings: false,
		              // Disabled because of an issue with Uglify breaking seemingly valid code:
		              // https://github.com/facebook/create-react-app/issues/2376
		              // Pending further investigation:
		              // https://github.com/mishoo/UglifyJS2/issues/2011
		              comparisons: false,
		              // Disabled because of an issue with Terser breaking valid code:
		              // https://github.com/facebook/create-react-app/issues/5250
		              // Pending further investigation:
		              // https://github.com/terser-js/terser/issues/120
		              inline: 2,
		            },
					keep_classnames: false,
					keep_fnames: false,
					mangle: {
						reserved: isI18n ? [ 'translate' ] : [],
						safari10: true,
					},
					output: {
						ecma: 5,
						comments: false,
						// Turned on because emoji and regex is not minified properly using default
						// https://github.com/facebook/create-react-app/issues/2488
						ascii_only: true,
					}
				},
			} ),
			new OptimizeCSSAssetsPlugin({
	          cssProcessorOptions: {
	            parser: safePostCssParser,
	            map: false,
	          },
	          cssProcessorPluginOptions: {
	            preset: ['default', { minifyFontValues: { removeQuotes: false } }],
	          },
	        }),
		],
	},
	performance: { hints: false },
	devServer: {
		contentBase: false,
		overlay: {
			errors: true,
			warnings: false,
		},
		disableHostCheck: true,
		headers: {
			'Access-Control-Allow-Origin': '*'
		},
		hot: true,
		liveReload: false,
	},
	externals: Object.assign(
		{},
		{
			'jquery': 'jQuery',
			'cheerio': 'window',
			'react/addons': true,
			'react/lib/ExecutionEnvironment': true,
			'react/lib/ReactContext': true,
			moment: 'moment',
			lodash: 'lodash',
			// WordPress components are too heavy to extern because we only use a few components.
		},
		! isDev ? {
			// Dev mode cannot use external react-dom because HMR requires patched @hot-loader/react-dom
			react: 'React',
			'react-dom': 'ReactDOM',
		} : undefined
	),
	module: {
		rules: [
			{
				parser: { amd: false },
			},
			{
				test: /\.jsx?$/,
				enforce: 'pre',
				use: {
					loader: 'eslint-loader',
					options: {
						emitWarning: true,
					},
				},
				include: path.resolve( __dirname, 'client' ),
			},
			{
				test: /\.scss$/,
				include: path.resolve( __dirname, 'assets', 'stylesheets' ),
				use: cssLoaders,
			},
			{
				test: /\.scss$/,
				include: [
					path.resolve( __dirname, 'client' ),
					path.resolve( __dirname, 'wp-calypso', 'client' ),
				],
				use: cssLoaders.concat( [
					{
						loader: 'wrap-loader',
						options: {
							before: [
								"@import 'shared/utils';\n" +
								"@import 'colors';\n" +
								"@import '~@automattic/calypso-color-schemes/src/shared/color-schemes';\n" +
								"@import '~@automattic/color-studio/dist/color-variables';\n",
								'.wp-core-ui.wp-admin .wcc-root {',
							],
							after: '}',
						},
					},
				] ),
			},
			{
				test: /\.html$/,
				use: 'html-loader',
			},
			{
				test: /\.jsx?$/,
				enforce: 'post',
				use: [
					{
						loader: 'thread-loader',
						options: {
							workers: Math.max( 2, Math.floor( os.cpus().length / 2 ) ),
						},
					},
					{
						loader: 'babel-loader',
						options: {
							configFile: path.resolve( __dirname, 'wp-calypso', 'babel.config.js' ),
							cacheDirectory: true,
							cacheIdentifier: require( './wp-calypso/server/bundler/babel/babel-loader-cache-identifier' ),
							plugins: ["react-hot-loader/babel"]
						},
					}
				],
				include: [
					path.resolve( __dirname, 'client' ),
					path.resolve( __dirname, 'wp-calypso', 'client' ),
				],
			},
			{
				test: /\.(png|jpe?g|gif|svg)$/i,
				use: [
				  {
					loader: 'file-loader',
						options: {
						outputPath: 'images',
					},
				  },
				],
			},
		],
	},
	resolve: {
		extensions: [ '.json', '.js', '.jsx' ],
		modules: [
			path.resolve( __dirname, 'client' ),
			path.resolve( __dirname, 'client', 'calypso-stubs' ),
			path.resolve( __dirname, 'client', 'calypso-stubs', 'extensions' ),
			path.resolve( __dirname, 'node_modules' ),
			path.resolve( __dirname, 'client', 'extensions' ),
			path.resolve( __dirname, 'wp-calypso', 'client' ),
			path.resolve( __dirname, 'wp-calypso', 'node_modules' ),
		],
		symlinks: false,
		alias: {
			...coreE2EAlias,
			'react-dom': '@hot-loader/react-dom',
			'wcs-client': path.resolve( __dirname, 'client' ),
		},
	},
	node: {
		fs: 'empty',
	},
	plugins: [
		new webpack.ProvidePlugin( {
			'fetch': 'imports-loader?this=>global!exports-loader?global.fetch!whatwg-fetch',
		} ),
		! isDev && new MiniCssExtractPlugin( {
			filename: '[name]-' + process.env.npm_package_version + '.css',
			chunkFilename: 'chunks/[chunkhash].css'
		} ),
		new webpack.DefinePlugin( {
			'process.env.NODE_ENV': isDev ? '"development"' : '"production"',
			'typeof window': '"object"',
			global: 'window',
		} ),
		new webpack.LoaderOptionsPlugin( { minimize: ! isDev } ),
		new webpack.NormalModuleReplacementPlugin( /^path$/, 'path-browserify' ),
		new webpack.IgnorePlugin( /^props$/ ),
		new webpack.IgnorePlugin( /^\.\/locale$/, /moment$/ ),
		new MomentTimezoneDataPlugin( {
			startYear: 2000,
		} ),
		process.env.ANALYZE && new BundleAnalyzerPlugin(),
	].filter(Boolean),
};
