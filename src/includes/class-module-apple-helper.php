<?php

namespace League\OAuth2\Client\Provider {

	class Apple2 extends Apple
	{
		public function getLocalKey()
		{
			$content = get_option( 'siwe_apple_client_secret' );
			return \Lcobucci\JWT\Signer\Key\InMemory::plainText($content);
		}
	}
}
