<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService;

use InvalidArgumentException;
use MediaWiki\Extension\EmbedVideo\EmbedService\Spotify\SpotifyAlbum;
use MediaWiki\Extension\EmbedVideo\EmbedService\Spotify\SpotifyArtist;
use MediaWiki\Extension\EmbedVideo\EmbedService\Spotify\SpotifyTrack;
use MediaWiki\Extension\EmbedVideo\EmbedService\Twitch\Twitch;
use MediaWiki\Extension\EmbedVideo\EmbedService\Twitch\TwitchClip;
use MediaWiki\Extension\EmbedVideo\EmbedService\Twitch\TwitchVod;
use MediaWiki\Extension\EmbedVideo\EmbedService\YouTube\YouTube;
use MediaWiki\Extension\EmbedVideo\EmbedService\YouTube\YouTubeOEmbed;
use MediaWiki\Extension\EmbedVideo\EmbedService\YouTube\YouTubePlaylist;
use MediaWiki\Extension\EmbedVideo\EmbedService\YouTube\YouTubeVideoList;

final class EmbedServiceFactory {

	/**
	 * List of all available services
	 *
	 * @var AbstractEmbedService[]
	 */
	private static $availableServices = [
		ArchiveOrg::class,
		SoundCloud::class,
		SpotifyAlbum::class,
		SpotifyArtist::class,
		SpotifyTrack::class,
		Twitch::class,
		TwitchClip::class,
		TwitchVod::class,
		Vimeo::class,
		YouTubeOEmbed::class,
		YouTube::class,
		YouTubePlaylist::class,
		YouTubeVideoList::class,
	];

	/**
	 * @param string $serviceName
	 * @param string $id
	 * @return AbstractEmbedService
	 */
	public static function newFromName( string $serviceName, string $id ): AbstractEmbedService {
		switch ( strtolower( $serviceName ) ) {
			case 'archiveorg':
				return new ArchiveOrg( $id );

			case 'soundcloud':
				return new SoundCloud( $id );

			case 'spotifyalbum':
				return new SpotifyAlbum( $id );

			case 'spotifyartist':
				return new SpotifyArtist( $id );

			case 'spotify':
			case 'spotifytrack':
				return new SpotifyTrack( $id );

			case 'twitch':
				return new Twitch( $id );

			case 'twitchclip':
				return new TwitchClip( $id );

			case 'twitchvod':
				return new TwitchVod( $id );

			case 'vimeo':
				return new Vimeo( $id );

			case 'youtubeoembed':
				return new YouTubeOEmbed( $id );

			case 'youtube':
				return new YouTube( $id );

			case 'youtubeplaylist':
				return new YouTubePlaylist( $id );

			case 'youtubevideolist':
				return new YouTubeVideoList( $id );

			default:
				throw new InvalidArgumentException( sprintf( 'VideoService "%s" not recognized.', $serviceName ) );
		}
	}

	/**
	 * @return AbstractEmbedService[]
	 */
	public static function getAvailableServices(): array {
		return self::$availableServices;
	}
}
