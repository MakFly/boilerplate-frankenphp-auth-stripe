<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Attribute\AccountOTP;
use App\DTO\Auth\LoginRequest;
use App\DTO\Auth\OtpRequest;
use App\DTO\Auth\RegisterRequest;
use App\DTO\Response\ApiResponse;
use App\Entity\User;
use App\Enum\ApiMessage;
use App\Exception\Auth\RegistrationException;
use App\Interface\Auth\AuthInterface;
use Doctrine\ORM\EntityManagerInterface;
use Google_Client;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    private Google_Client $googleClient;

    public function __construct(
        private readonly AuthInterface $authInterface,
        private readonly LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {
        $this->googleClient = new Google_Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[AccountOTP] // Désactivation|Activation de l'OTP
    public function login(
        #[MapRequestPayload] LoginRequest $request
    ): JsonResponse {

        $result = $this->authInterface->authCustom($request);

        if (!is_array($result)) {
            return ApiResponse::noContent();
        }

        return ApiResponse::success($result);
    }

    #[Route('/otp', name: 'otp_verify', methods: ['POST'])]
    public function verifyOtp(
        #[MapRequestPayload] OtpRequest $request
    ): JsonResponse {
        /**
         * @TODO Vérifier le code OTP et renvoyer un token JWT
         * via : email & le code OTP reçu
         */
        $token = $this->authInterface->verifyOtp($request->getEmail(), $request->getCode());

        return ApiResponse::success($token);
    }

    // verify jwt token
    #[Route('/verify/jwt', name: 'verify_jwt', methods: ['POST'])]
    public function verifyJwt(
        Request $request
    ): JsonResponse {
        $token = $request->headers->get('Authorization');

        if (!$token) {
            return ApiResponse::error(ApiMessage::INVALID_TOKEN, null, Response::HTTP_UNAUTHORIZED);
        }

        $token = str_replace('Bearer ', '', $token);
        $result = $this->authInterface->verifyJwt($token);

        return ApiResponse::success($result);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterRequest $request
    ): JsonResponse {
        $errors = $this->validator->validate($request);

        if (count($errors) > 0) {
            return ApiResponse::error(
                ApiMessage::INVALID_DATA,
                null,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $result = $this->authInterface->register(
                $request->email,
                $request->password,
                $request->username
            );

            $this->logger->info('Inscription réussie', ['email' => $request->email]);

            return ApiResponse::success($result);
        } catch (RegistrationException $e) {
            $this->logger->error('Échec de l\'inscription', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            $statusCode = match ($e->getMessage()) {
                ApiMessage::EMAIL_ALREADY_USED->value => Response::HTTP_BAD_REQUEST,
                default => Response::HTTP_UNPROCESSABLE_ENTITY,
            };

            return ApiResponse::error(
                match ($e->getMessage()) {
                    ApiMessage::EMAIL_ALREADY_USED->value => ApiMessage::EMAIL_ALREADY_USED,
                    ApiMessage::INVALID_PASSWORD_FORMAT->value => ApiMessage::INVALID_PASSWORD_FORMAT,
                    default => ApiMessage::INVALID_DATA,
                },
                null,
                $statusCode
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue lors de l\'inscription', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return ApiResponse::error(
                ApiMessage::REGISTRATION_FAILED,
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/sso', name: 'sso', methods: ['POST'])]
    public function sso(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['account']['id_token'])) {
            return new JsonResponse(['error' => 'Données invalides'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $idToken = $data['account']['id_token'];
        // Vérification du token Google
        $payload = $this->googleClient->verifyIdToken($idToken);
        if (!$payload) {
            return new JsonResponse(['error' => 'Token invalide'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Récupération des infos Google
        $googleId = $payload['sub'];
        $email    = $payload['email'] ?? null;
        $name     = $payload['name'] ?? '';

        // Recherche de l'utilisateur en base par googleId
        $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => $googleId]);
        if (!$user) {
            $user = new User();
            $user->setGoogleId($googleId);
            $user->setEmail($email);
            $user->setUsername($name);
        } else {
            // Mise à jour éventuelle
            $user->setEmail($email);
            $user->setUsername($name);
        }

        $this->em->persist($user);
        $this->em->flush();

        // Retourne un résultat (par exemple, tu pourrais générer ton propre JWT ici)
        return new JsonResponse([
            'status' => 'ok',
            'user' => [
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'name'  => $user->getName(),
            ]
        ]);
    }
}
