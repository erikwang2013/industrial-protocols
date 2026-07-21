<?php

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Services;

use Erikwang2013\IndustrialProtocols\OpcUa\Encoding\BinaryDecoder;
use Erikwang2013\IndustrialProtocols\OpcUa\Encoding\BinaryEncoder;
use Erikwang2013\IndustrialProtocols\OpcUa\Exception\OpcUaException;
use Erikwang2013\IndustrialProtocols\OpcUa\Transport\SecureChannel;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\NodeId;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\Variant;

/**
 * OPC UA Session Manager.
 *
 * Manages the lifecycle of an OPC UA session: CreateSession, ActivateSession,
 * and provides high-level service wrappers for Read, Write, and Browse.
 */
class SessionManager
{
    private int $sessionId = 0;
    private ?string $authenticationToken = null;

    public function __construct(
        private SecureChannel $channel,
    ) {}

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    public function getAuthenticationToken(): ?string
    {
        return $this->authenticationToken;
    }

    /**
     * Create a session with the OPC UA server.
     *
     * Sends a CreateSessionRequest and parses the response to extract
     * the session ID and authentication token.
     *
     * @throws OpcUaException on service-level error
     */
    public function createSession(
        string $applicationUri,
        string $sessionName = 'PHP-OPCUA',
    ): void {
        $enc = new BinaryEncoder();

        // TypeId
        $enc->writeNodeId(new NodeId(0, SecureChannel::SERVICE_CREATE_SESSION));

        // RequestHeader
        $enc->writeNodeId(new NodeId(0, 0));         // authenticationToken (null for CreateSession)
        $enc->writeInt64(0);                          // timestamp
        $enc->writeUInt32(0);                         // requestHandle
        $enc->writeUInt32(0);                         // returnDiagnostics
        $enc->writeString('');                        // auditEntryId
        $enc->writeUInt32(0);                         // timeoutHint
        $enc->writeNodeId(new NodeId(0, 0));          // additionalHeader TypeId
        $enc->writeByte(0);                           // additionalHeader Encoding

        // ClientDescription
        $enc->writeNodeId(new NodeId(1, $applicationUri)); // applicationUri as NodeId
        $enc->writeString('');                              // productUri
        $enc->writeString($sessionName);                    // applicationName (LocalizedText)
        $enc->writeString('en');                            // locale

        // ServerUri
        $enc->writeString('');

        // EndpointUrl
        $enc->writeString('');

        // SessionName
        $enc->writeString($sessionName);

        // ClientNonce
        $enc->writeByteString(chr(0) . chr(1) . chr(2) . chr(3));

        // ClientCertificate
        $enc->writeByteString('');

        // RequestedSessionTimeout
        $enc->writeDouble(3600000.0);

        // MaxResponseMessageSize
        $enc->writeUInt32(0);

        $data = $this->channel->sendRequest(
            $enc->toBytes(),
            SecureChannel::SERVICE_CREATE_SESSION,
        );

        $dec = new BinaryDecoder($data);

        // ResponseHeader
        $dec->readNodeId();    // TypeId
        $dec->readInt64();     // Timestamp
        $dec->readUInt32();    // RequestHandle
        $sc = $dec->readStatusCode();
        if (!$sc->isGood()) {
            throw OpcUaException::fromStatusCode($sc->code);
        }

        // ServiceDiagnostics + StringTable + AdditionalHeader (skip)
        $dec->readString();    // diagnosticInfo string
        $dec->readString();    // stringTable
        $dec->readNodeId();    // additionalHeader TypeId
        $dec->readByte();      // additionalHeader Encoding

        // CreateSessionResponse body
        $this->sessionId = $dec->readUInt32();
        $this->authenticationToken = bin2hex($dec->readByteString());
        $dec->readDouble();    // revisedSessionTimeout
        $dec->readUInt32();    // maxRequestMessageSize
        $dec->readByteString(); // serverNonce
        $dec->readByteString(); // serverCertificate
    }

    /**
     * Activate the session.
     *
     * Must be called after createSession().
     *
     * @throws OpcUaException on service-level error
     */
    public function activateSession(): void
    {
        $enc = new BinaryEncoder();

        // TypeId
        $enc->writeNodeId(new NodeId(0, SecureChannel::SERVICE_ACTIVATE_SESSION));

        // RequestHeader — uses the authentication token from CreateSession
        $enc->writeNodeId(new NodeId(0, $this->sessionId));
        $enc->writeInt64(0);
        $enc->writeUInt32(0);
        $enc->writeUInt32(0);
        $enc->writeString('');
        $enc->writeUInt32(0);
        $enc->writeNodeId(new NodeId(0, 0));
        $enc->writeByte(0);

        // ClientSignature
        $enc->writeString('');          // algorithm (null for None security)
        $enc->writeByteString('');      // signature

        // ClientSoftwareCertificates
        $enc->writeInt32(-1);           // null array (length = -1)

        // LocaleIds
        $enc->writeInt32(1);            // array count
        $enc->writeString('en');

        // UserIdentityToken (Anonymous)
        $enc->writeNodeId(new NodeId(0, 321));   // AnonymousIdentityToken
        $enc->writeByte(1);                        // encoding
        $enc->writeString('anonymous');            // policyId

        $data = $this->channel->sendRequest(
            $enc->toBytes(),
            SecureChannel::SERVICE_ACTIVATE_SESSION,
        );

        $dec = new BinaryDecoder($data);

        // ResponseHeader
        $dec->readNodeId();
        $dec->readInt64();
        $dec->readUInt32();
        $sc = $dec->readStatusCode();
        if (!$sc->isGood()) {
            throw OpcUaException::fromStatusCode($sc->code);
        }
    }

    /**
     * Read one or more node attributes.
     *
     * @param array<int, array{nodeId: NodeId, attributeId?: int}> $nodes
     * @return array<int, array{statusCode: int, value: mixed}>
     *
     * @throws OpcUaException on service-level error
     */
    public function read(array $nodes): array
    {
        $enc = new BinaryEncoder();

        // TypeId
        $enc->writeNodeId(new NodeId(0, SecureChannel::SERVICE_READ));

        // RequestHeader
        $enc->writeNodeId(new NodeId(0, $this->sessionId));
        $enc->writeInt64(0);
        $enc->writeUInt32(0);
        $enc->writeUInt32(0);
        $enc->writeString('');
        $enc->writeUInt32(0);
        $enc->writeNodeId(new NodeId(0, 0));
        $enc->writeByte(0);

        // MaxAge
        $enc->writeDouble(0.0);

        // TimestampsToReturn
        $enc->writeInt32(0); // Neither

        // NodesToRead array
        $enc->writeInt32(count($nodes));
        foreach ($nodes as $node) {
            $enc->writeNodeId($node['nodeId']);
            $enc->writeUInt32($node['attributeId'] ?? 13); // 13 = Value
        }

        $data = $this->channel->sendRequest(
            $enc->toBytes(),
            SecureChannel::SERVICE_READ,
        );

        $dec = new BinaryDecoder($data);

        // ResponseHeader
        $dec->readNodeId();
        $dec->readInt64();
        $dec->readUInt32();
        $sc = $dec->readStatusCode();
        if (!$sc->isGood()) {
            throw OpcUaException::fromStatusCode($sc->code);
        }
        $dec->readString();
        $dec->readString();
        $dec->readNodeId();
        $dec->readByte();

        // Results array
        $results = [];
        $count = $dec->readInt32();
        for ($i = 0; $i < $count; $i++) {
            $sc2 = $dec->readStatusCode();
            $value = null;
            if ($sc2->isGood()) {
                $encMask = $dec->readByte();
                if ($encMask & 0x01) {
                    // Variant: skip the encoded variant body for now
                    $dec->readByte(); // variant type mask
                    $value = '0x' . dechex($sc2->code);
                }
            }
            $results[] = [
                'statusCode' => $sc2->code,
                'value'      => $value,
            ];
        }

        return $results;
    }

    /**
     * Browse the server address space from a given node.
     *
     * @return string[] Array of NodeId strings found under the given node
     *
     * @throws OpcUaException on service-level error
     */
    public function browse(NodeId $nodeId): array
    {
        $enc = new BinaryEncoder();

        // TypeId
        $enc->writeNodeId(new NodeId(0, SecureChannel::SERVICE_BROWSE));

        // RequestHeader
        $enc->writeNodeId(new NodeId(0, $this->sessionId));
        $enc->writeInt64(0);
        $enc->writeUInt32(0);
        $enc->writeUInt32(0);
        $enc->writeString('');
        $enc->writeUInt32(0);
        $enc->writeNodeId(new NodeId(0, 0));
        $enc->writeByte(0);

        // View
        $enc->writeNodeId(new NodeId(0, 0));   // viewId (null)
        $enc->writeUInt32(0);                   // requestedMaxReferencesPerNode

        // BrowseDescription
        $enc->writeInt32(1);                    // browseDirection (Forward)
        $enc->writeNodeId($nodeId);             // nodeId
        $enc->writeUInt32(0x3F);                // includeSubtypes mask (all reference types)
        $enc->writeUInt32(0);                   // nodeClassMask (all classes)
        $enc->writeInt32(0);                    // resultMask (all fields)

        $data = $this->channel->sendRequest(
            $enc->toBytes(),
            SecureChannel::SERVICE_BROWSE,
        );

        $dec = new BinaryDecoder($data);

        // ResponseHeader
        $dec->readNodeId();
        $dec->readInt64();
        $dec->readUInt32();
        $sc = $dec->readStatusCode();
        if (!$sc->isGood()) {
            throw OpcUaException::fromStatusCode($sc->code);
        }
        $dec->readString();
        $dec->readString();
        $dec->readNodeId();
        $dec->readByte();

        // BrowseResults array
        $results = [];
        $count = $dec->readInt32();
        for ($i = 0; $i < $count; $i++) {
            $dec->readStatusCode();                 // per-result status
            $dec->readByteString();                 // continuationPoint (ByteString, not NodeId)
            $refCount = $dec->readInt32();
            for ($j = 0; $j < $refCount; $j++) {
                $refTypeId = $dec->readNodeId();     // ReferenceTypeId
                $dec->readBoolean();                 // IsForward
                $refNodeId = $dec->readNodeId();     // ExpandedNodeId (the actual target node)
                $results[] = $refNodeId->toString();
                $dec->readString();                  // displayName
                $dec->readUInt32();                  // nodeClass
                $dec->readNodeId();                  // typeDefinition
            }
        }

        return $results;
    }

    /**
     * Write values to nodes.
     *
     * @param array<int, array{nodeId: NodeId, attributeId?: int, value: Variant}> $nodes
     * @return array<int, array{statusCode: int}>
     *
     * @throws OpcUaException on service-level error
     */
    public function write(array $nodes): array
    {
        $enc = new BinaryEncoder();

        // TypeId
        $enc->writeNodeId(new NodeId(0, SecureChannel::SERVICE_WRITE));

        // RequestHeader
        $enc->writeNodeId(new NodeId(0, $this->sessionId));
        $enc->writeInt64(0);
        $enc->writeUInt32(0);
        $enc->writeUInt32(0);
        $enc->writeString('');
        $enc->writeUInt32(0);
        $enc->writeNodeId(new NodeId(0, 0));
        $enc->writeByte(0);

        // NodesToWrite array
        $enc->writeInt32(count($nodes));
        foreach ($nodes as $node) {
            $enc->writeNodeId($node['nodeId']);
            $enc->writeUInt32($node['attributeId'] ?? 13);
            $enc->writeString('');                  // indexRange
            $enc->writeByte(0x01);                  // value encoding mask
            $enc->writeBytes($node['value']->encode());
        }

        $data = $this->channel->sendRequest(
            $enc->toBytes(),
            SecureChannel::SERVICE_WRITE,
        );

        $dec = new BinaryDecoder($data);

        // ResponseHeader
        $dec->readNodeId();
        $dec->readInt64();
        $dec->readUInt32();
        $sc = $dec->readStatusCode();
        if (!$sc->isGood()) {
            throw OpcUaException::fromStatusCode($sc->code);
        }
        $dec->readString();
        $dec->readString();
        $dec->readNodeId();
        $dec->readByte();

        // WriteResults array
        $results = [];
        $count = $dec->readInt32();
        for ($i = 0; $i < $count; $i++) {
            $results[] = [
                'statusCode' => $dec->readStatusCode()->code,
            ];
        }

        return $results;
    }
}
