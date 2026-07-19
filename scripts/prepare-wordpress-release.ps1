<#
.SYNOPSIS
Prepares a Pull Quotes release in a local WordPress.org SVN working copy.

.DESCRIPTION
Validates the requested release version, checks the committed Git source,
runs the PHP and JavaScript release checks, synchronizes the runtime allowlist
into SVN trunk, copies WordPress.org directory assets, sets their MIME types,
and creates a numbered SVN tag from trunk.

The script changes only the local SVN working copy. It never runs svn commit,
creates a Git tag, pushes Git changes, or confirms a WordPress.org release.
It prints the commands for those reviewed, manual steps when preparation
finishes.

.PARAMETER Version
The semantic release version to prepare, such as 2.0.0. It must match the
plugin header, PULL_QUOTES_VERSION constant, readme Stable Tag, and
package.json version.

.PARAMETER SvnPath
The path to the root of the Pull Quotes WordPress.org SVN working copy. It must
contain .svn, trunk, tags, and assets. The default is the sibling
pullquotes-svn directory next to the Git repository.

.PARAMETER SvnExe
The path to svn.exe. When omitted, the script first checks PATH and then the
standard TortoiseSVN installation path.

.PARAMETER SkipChecks
Skips PHP lint, PHPCS, JavaScript lint, and the production build. Intended only
for testing the helper itself, not for preparing a real release.

.PARAMETER SkipSvnUpdate
Skips svn update. Intended for isolated or disposable working-copy tests, not
for preparing a real release.

.PARAMETER AllowDirtyGit
Allows tracked Git changes. Untracked files never block preparation. This
override is intended only for testing the helper before it is committed.

.PARAMETER AllowDirtySvn
Allows existing SVN working-copy changes. Use only after reviewing those
changes because they may be included in the prepared release.

.PARAMETER ShowDiff
Prints the full SVN diff after preparation. Without this switch, the script
prints the command that can be used to review the diff separately.

.PARAMETER Help
Displays this full help text and exits without preparing a release.

.PARAMETER HelpArgument
Accepts the literal --help command-line argument and displays this full help
text without preparing a release.

.EXAMPLE
.\scripts\prepare-wordpress-release.ps1 -Version 2.0.0

Prepares version 2.0.0 using the default sibling pullquotes-svn working copy.

.EXAMPLE
.\scripts\prepare-wordpress-release.ps1 -Version 2.0.0 -SvnPath 'D:\wordpress-svn\pull-quotes' -ShowDiff

Prepares version 2.0.0 in a specified SVN working copy and prints the full SVN
diff when finished.

.EXAMPLE
Get-Help .\scripts\prepare-wordpress-release.ps1 -Full

Displays the complete native PowerShell help, including syntax, parameters,
and examples.

.EXAMPLE
.\scripts\prepare-wordpress-release.ps1 --help

Displays the same full help without requiring a release version.
#>
[CmdletBinding( DefaultParameterSetName = 'Release' )]
param(
	[Parameter( Mandatory, ParameterSetName = 'Release' )]
	[ValidatePattern( '^\d+\.\d+\.\d+$' )]
	[string] $Version,

	[string] $SvnPath = ( Join-Path $PSScriptRoot '..\..\pullquotes-svn' ),

	[string] $SvnExe,

	[switch] $SkipChecks,

	[switch] $SkipSvnUpdate,

	[switch] $AllowDirtyGit,

	[switch] $AllowDirtySvn,

	[switch] $ShowDiff,

	[Parameter( Mandatory, ParameterSetName = 'HelpSwitch' )]
	[Alias( 'h', '?' )]
	[switch] $Help,

	[Parameter( Mandatory, Position = 0, ParameterSetName = 'HelpArgument' )]
	[ValidateSet( '--help' )]
	[string] $HelpArgument
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

if ( $Help -or $HelpArgument ) {
	Get-Help $PSCommandPath -Full
	exit 0
}

function Invoke-CheckedCommand {
	param(
		[Parameter( Mandatory )]
		[string] $Command,

		[string[]] $Arguments = @(),

		[string] $WorkingDirectory
	)

	if ( $WorkingDirectory ) {
		Push-Location $WorkingDirectory
	}

	try {
		& $Command @Arguments
		if ( 0 -ne $LASTEXITCODE ) {
			throw "Command failed with exit code ${LASTEXITCODE}: $Command $Arguments"
		}
	} finally {
		if ( $WorkingDirectory ) {
			Pop-Location
		}
	}
}

function Get-CheckedCommandOutput {
	param(
		[Parameter( Mandatory )]
		[string] $Command,

		[string[]] $Arguments = @(),

		[string] $WorkingDirectory
	)

	if ( $WorkingDirectory ) {
		Push-Location $WorkingDirectory
	}

	try {
		$output = & $Command @Arguments
		if ( 0 -ne $LASTEXITCODE ) {
			throw "Command failed with exit code ${LASTEXITCODE}: $Command $Arguments"
		}
		return $output
	} finally {
		if ( $WorkingDirectory ) {
			Pop-Location
		}
	}
}

function Remove-SvnItem {
	param(
		[Parameter( Mandatory )]
		[string] $Path
	)

	Invoke-CheckedCommand $script:SvnCommand @(
		'delete',
		'--force',
		'--',
		$Path
	)
}

function Sync-SvnDirectory {
	param(
		[Parameter( Mandatory )]
		[string] $Source,

		[Parameter( Mandatory )]
		[string] $Destination
	)

	if ( -not ( Test-Path -LiteralPath $Destination ) ) {
		New-Item -ItemType Directory -Path $Destination | Out-Null
	}

	$sourceRoot = ( Resolve-Path -LiteralPath $Source ).Path
	$sourceFiles = Get-ChildItem -LiteralPath $sourceRoot -Recurse -File
	$expectedFiles = [System.Collections.Generic.HashSet[string]]::new(
		[System.StringComparer]::OrdinalIgnoreCase
	)
	$expectedDirectories = [System.Collections.Generic.HashSet[string]]::new(
		[System.StringComparer]::OrdinalIgnoreCase
	)

	foreach ( $file in $sourceFiles ) {
		$relativePath = $file.FullName.Substring( $sourceRoot.Length + 1 )
		$expectedFiles.Add( $relativePath ) | Out-Null

		$parent = Split-Path -Parent $relativePath
		while ( $parent ) {
			$expectedDirectories.Add( $parent ) | Out-Null
			$parent = Split-Path -Parent $parent
		}
	}

	$destinationRoot = ( Resolve-Path -LiteralPath $Destination ).Path
	$staleDirectories = Get-ChildItem -LiteralPath $destinationRoot -Recurse -Directory |
		Sort-Object { $_.FullName.Length }

	foreach ( $directory in $staleDirectories ) {
		if ( -not ( Test-Path -LiteralPath $directory.FullName ) ) {
			continue
		}

		$relativePath = $directory.FullName.Substring( $destinationRoot.Length + 1 )
		if ( -not $expectedDirectories.Contains( $relativePath ) ) {
			Remove-SvnItem $directory.FullName
		}
	}

	$destinationFiles = Get-ChildItem -LiteralPath $destinationRoot -Recurse -File
	foreach ( $file in $destinationFiles ) {
		$relativePath = $file.FullName.Substring( $destinationRoot.Length + 1 )
		if ( -not $expectedFiles.Contains( $relativePath ) ) {
			Remove-SvnItem $file.FullName
		}
	}

	foreach ( $file in $sourceFiles ) {
		$relativePath = $file.FullName.Substring( $sourceRoot.Length + 1 )
		$destinationFile = Join-Path $destinationRoot $relativePath
		$destinationDirectory = Split-Path -Parent $destinationFile

		if ( -not ( Test-Path -LiteralPath $destinationDirectory ) ) {
			New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null
		}

		Copy-Item -LiteralPath $file.FullName -Destination $destinationFile -Force
	}
}

$repoRoot = ( Resolve-Path ( Join-Path $PSScriptRoot '..' ) ).Path
$svnRoot = ( Resolve-Path -LiteralPath $SvnPath ).Path

if ( -not ( Test-Path -LiteralPath ( Join-Path $svnRoot '.svn' ) ) ) {
	throw "Not an SVN working-copy root: $svnRoot"
}

if ( -not $SvnExe ) {
	$svnFromPath = Get-Command svn -ErrorAction SilentlyContinue
	$tortoiseSvn = 'C:\Program Files\TortoiseSVN\bin\svn.exe'

	if ( $svnFromPath ) {
		$SvnExe = $svnFromPath.Source
	} elseif ( Test-Path -LiteralPath $tortoiseSvn ) {
		$SvnExe = $tortoiseSvn
	} else {
		throw 'Could not find svn.exe. Pass its path with -SvnExe.'
	}
}

$script:SvnCommand = ( Resolve-Path -LiteralPath $SvnExe ).Path

$svnUrl = (
	Get-CheckedCommandOutput $script:SvnCommand @(
		'info',
		'--show-item',
		'url',
		$svnRoot
	)
).Trim()

if ( 'https://plugins.svn.wordpress.org/pull-quotes' -ne $svnUrl.TrimEnd( '/' ) ) {
	throw "Unexpected SVN repository URL: $svnUrl"
}

$trackedChanges = Get-CheckedCommandOutput git @(
	'-C',
	$repoRoot,
	'status',
	'--porcelain',
	'--untracked-files=no'
)

if ( $trackedChanges -and -not $AllowDirtyGit ) {
	throw 'The Git repository has tracked changes. Commit or restore them before preparing a release.'
}

$pluginContents = Get-Content -LiteralPath ( Join-Path $repoRoot 'pull-quotes.php' ) -Raw
$readmeContents = Get-Content -LiteralPath ( Join-Path $repoRoot 'readme.txt' ) -Raw
$package = Get-Content -LiteralPath ( Join-Path $repoRoot 'package.json' ) -Raw | ConvertFrom-Json

$headerVersion = [regex]::Match(
	$pluginContents,
	'(?m)^\s*\*\s*Version:\s*([^\s]+)\s*$'
).Groups[1].Value
$constantVersion = [regex]::Match(
	$pluginContents,
	"define\(\s*'PULL_QUOTES_VERSION',\s*'([^']+)'\s*\)"
).Groups[1].Value
$stableTag = [regex]::Match(
	$readmeContents,
	'(?im)^Stable tag:\s*([^\s]+)\s*$'
).Groups[1].Value

$versions = [ordered]@{
	'Plugin header' = $headerVersion
	'PHP constant'  = $constantVersion
	'Stable tag'    = $stableTag
	'package.json'  = $package.version
}

foreach ( $entry in $versions.GetEnumerator() ) {
	if ( $Version -ne $entry.Value ) {
		throw "$($entry.Key) version '$($entry.Value)' does not match requested version '$Version'."
	}
}

Write-Host "Preparing Pull Quotes $Version"
Write-Host "Git source: $repoRoot"
Write-Host "SVN working copy: $svnRoot"

if ( $AllowDirtyGit ) {
	Write-Warning 'AllowDirtyGit is enabled. Use this only while testing the release helper.'
}

if ( $AllowDirtySvn ) {
	Write-Warning 'AllowDirtySvn is enabled. Existing SVN changes may be included in the prepared release.'
}

if ( $SkipChecks ) {
	Write-Warning 'Release checks are being skipped.'
}

if ( $SkipSvnUpdate ) {
	Write-Warning 'The SVN update is being skipped.'
}

if ( -not $SkipChecks ) {
	Write-Host 'Running release checks...'

	$phpFiles = @(
		Join-Path $repoRoot 'pull-quotes.php'
	) + @(
		Get-ChildItem -LiteralPath ( Join-Path $repoRoot 'includes' ) -Filter '*.php' -File |
			ForEach-Object { $_.FullName }
	)

	foreach ( $phpFile in $phpFiles ) {
		Invoke-CheckedCommand php @( '-l', $phpFile )
	}

	Invoke-CheckedCommand composer @( 'lint' ) $repoRoot
	Invoke-CheckedCommand npm @( 'run', 'lint:js' ) $repoRoot
	Invoke-CheckedCommand npm @( 'run', 'build' ) $repoRoot

	$generatedChanges = Get-CheckedCommandOutput git @(
		'-C',
		$repoRoot,
		'status',
		'--porcelain',
		'--untracked-files=no'
	)

	if ( $generatedChanges -and -not $AllowDirtyGit ) {
		throw 'Release checks changed tracked files. Review and commit the generated output, then rerun.'
	}
}

$svnChanges = Get-CheckedCommandOutput $script:SvnCommand @( 'status', $svnRoot )
if ( $svnChanges -and -not $AllowDirtySvn ) {
	throw "The SVN working copy has local changes:`n$svnChanges`nUse a clean working copy or pass -AllowDirtySvn after reviewing them."
}

if ( -not $SkipSvnUpdate ) {
	Invoke-CheckedCommand $script:SvnCommand @( 'update', $svnRoot )
}

$tagPath = Join-Path ( Join-Path $svnRoot 'tags' ) $Version
if ( Test-Path -LiteralPath $tagPath ) {
	throw "SVN tag already exists: $tagPath"
}

$trunkPath = Join-Path $svnRoot 'trunk'
$allowedTrunkItems = @(
	'pull-quotes.php',
	'readme.txt',
	'build',
	'css',
	'includes'
)

foreach ( $item in Get-ChildItem -LiteralPath $trunkPath -Force ) {
	if ( $item.Name -notin $allowedTrunkItems ) {
		Remove-SvnItem $item.FullName
	}
}

Copy-Item -LiteralPath ( Join-Path $repoRoot 'pull-quotes.php' ) -Destination $trunkPath -Force
Copy-Item -LiteralPath ( Join-Path $repoRoot 'readme.txt' ) -Destination $trunkPath -Force

foreach ( $directory in @( 'build', 'css', 'includes' ) ) {
	Sync-SvnDirectory (
		Join-Path $repoRoot $directory
	) (
		Join-Path $trunkPath $directory
	)
}

$assetsSource = Join-Path $repoRoot 'assets-wp-repo'
$assetsDestination = Join-Path $svnRoot 'assets'
Sync-SvnDirectory $assetsSource $assetsDestination

Invoke-CheckedCommand $script:SvnCommand @( 'add', '--force', $trunkPath, $assetsDestination )

foreach ( $png in Get-ChildItem -LiteralPath $assetsDestination -Filter '*.png' -File ) {
	Invoke-CheckedCommand $script:SvnCommand @(
		'propset',
		'svn:mime-type',
		'image/png',
		$png.FullName
	)
}

foreach ( $svg in Get-ChildItem -LiteralPath $assetsDestination -Filter '*.svg' -File ) {
	Invoke-CheckedCommand $script:SvnCommand @(
		'propset',
		'svn:mime-type',
		'image/svg+xml',
		$svg.FullName
	)
}

Invoke-CheckedCommand $script:SvnCommand @( 'copy', $trunkPath, $tagPath )

Write-Host ''
Write-Host 'Prepared SVN changes:'
Invoke-CheckedCommand $script:SvnCommand @( 'status', $svnRoot )

if ( $ShowDiff ) {
	Invoke-CheckedCommand $script:SvnCommand @( 'diff', $svnRoot )
} else {
	Write-Host ''
	Write-Host "Review the full diff with:"
	Write-Host "& '$script:SvnCommand' diff '$svnRoot'"
}

Write-Host ''
Write-Host 'Nothing has been published.'
Write-Host 'After reviewing the SVN status and diff, publish with:'
Write-Host "& '$script:SvnCommand' commit -m 'Release $Version' '$trunkPath' '$assetsDestination' '$tagPath'"
Write-Host ''
Write-Host 'After WordPress.org confirms the release, create the matching Git tag:'
Write-Host "git tag -a '$Version' -m 'Release $Version'"
Write-Host "git push origin master '$Version'"
