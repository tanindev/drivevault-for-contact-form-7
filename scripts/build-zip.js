const fs = require( 'fs' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );

const pluginSlug = 'drivevault-for-contact-form-7';
const zipFileName = `${ pluginSlug }.zip`;
const rootDir = path.resolve( __dirname, '..' );
const distDir = path.resolve( rootDir, 'dist' );
const pluginDistDir = path.resolve( distDir, pluginSlug );

console.log( '====================================================' );
console.log( '📦 DriveVault for Contact Form 7 - Production Release Build' );
console.log( '====================================================\n' );

// 1. Format Code
console.log( '🧹 [1/4] Formatting code with wp-scripts format...' );
try {
	execSync( 'npx wp-scripts format', { stdio: 'inherit', cwd: rootDir } );
	console.log( '  ✓ Code formatting complete.\n' );
} catch ( error ) {
	console.warn(
		'  ⚠️ Code formatting encountered warnings, continuing build...\n'
	);
}

// 2. Generate Languages / POT File
console.log( '🌐 [2/4] Generating translation language file (POT)...' );
try {
	execSync( 'node scripts/make-pot.js', { stdio: 'inherit', cwd: rootDir } );
	console.log( '  ✓ Language file generation complete.\n' );
} catch ( error ) {
	console.warn(
		'  ⚠️ POT generation encountered notice, using existing POT file.\n'
	);
}

// 3. Build Production Assets
console.log( '🚀 [3/4] Compiling production assets with wp-scripts...' );
try {
	execSync( 'npx wp-scripts build --webpack-copy-php', {
		stdio: 'inherit',
		cwd: rootDir,
	} );
	console.log( '  ✓ Production asset compilation complete.\n' );
} catch ( error ) {
	console.error( '  ❌ Error building assets:', error.message );
	process.exit( 1 );
}

// 4. Create Production Zip Package
console.log( `📦 [4/4] Packaging release archive into ${ zipFileName }...` );

// Clean dist directory & existing zip
if ( fs.existsSync( distDir ) ) {
	fs.rmSync( distDir, { recursive: true, force: true } );
}
if ( fs.existsSync( path.resolve( rootDir, zipFileName ) ) ) {
	fs.unlinkSync( path.resolve( rootDir, zipFileName ) );
}

fs.mkdirSync( pluginDistDir, { recursive: true } );

// Copy essential WordPress plugin files & folders
const includeItems = [
	'assets',
	'includes',
	'languages',
	'templates',
	'drivevault-for-contact-form-7.php',
	'readme.txt',
	'uninstall.php',
];

const copyRecursive = ( src, dest ) => {
	const stat = fs.statSync( src );
	if ( stat.isDirectory() ) {
		fs.mkdirSync( dest, { recursive: true } );
		fs.readdirSync( src ).forEach( ( child ) => {
			if (
				child.startsWith( '.' ) ||
				child === 'node_modules' ||
				child === 'dist' ||
				child.endsWith( '.map' )
			) {
				return;
			}
			copyRecursive( path.join( src, child ), path.join( dest, child ) );
		} );
	} else {
		fs.copyFileSync( src, dest );
	}
};

includeItems.forEach( ( item ) => {
	const srcPath = path.resolve( rootDir, item );
	const destPath = path.resolve( pluginDistDir, item );
	if ( fs.existsSync( srcPath ) ) {
		copyRecursive( srcPath, destPath );
	}
} );

// Zip the dist folder
try {
	execSync(
		`zip -rq "${ path.resolve( rootDir, zipFileName ) }" "${ pluginSlug }"`,
		{
			cwd: distDir,
			stdio: 'inherit',
		}
	);
	// Clean up dist directory after zipping to prevent duplicate files in repository
	fs.rmSync( distDir, { recursive: true, force: true } );

	console.log(
		`\n✅ Successfully generated production archive: ${ zipFileName }`
	);
} catch ( err ) {
	console.error( '❌ Failed to create zip package:', err.message );
}
