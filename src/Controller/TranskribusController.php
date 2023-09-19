<?php
// phpcs:disable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation

declare( strict_types = 1 );

namespace App\Controller;

use App\Engine\TranskribusClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Routing\Annotation\Route;

final class TranskribusController extends AbstractController {

	/**
	 * The main form and result page.
	 * @Route("/transkribus", name="transkribus")
	 * @param TranskribusClient $transkribusClient
	 * @return Response
	 */
	public function transkribus( TranskribusClient $transkribusClient ): Response {
		return $this->render( 'transkribus.html.twig', [
			'jobs' => $transkribusClient->getJobs(),
		] );
	}
}
