<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Vendor;

class DefaultVendors
{
    public static function register(VendorBridgeFactory $factory): void
    {
        // ── Beckhoff ──────────────────────────────
        $factory->register(new VendorProfile(
            name:       'beckhoff',
            protocol:   'ethercat',
            bridgeType: 'external-process',
            sdkPath:    '/opt/beckhoff/twincat/AdsApi/TcAdsDll',
            defaultPort: 48898, // ADS port
            homepage:   'https://www.beckhoff.com/twincat',
            description: 'Beckhoff TwinCAT — EtherCAT master with ADS protocol',
            devices: [
                new DeviceProfile('CX2030', '3.1', ['ads_netid' => '0.0.0.0.1.1'], 'Embedded PC, TwinCAT 3'),
                new DeviceProfile('CX5140', '3.1', ['ads_netid' => '0.0.0.0.1.1'], 'Embedded PC, high performance'),
                new DeviceProfile('C6015', '3.1', ['ads_netid' => '0.0.0.0.1.1'], 'Ultra-compact Industrial PC'),
                new DeviceProfile('C6030', '3.1', ['ads_netid' => '0.0.0.0.1.1'], 'Industrial PC, mid-range'),
                new DeviceProfile('EK1100', '2.x', ['ads_netid' => '0.0.0.0.1.1'], 'EtherCAT Coupler'),
                new DeviceProfile('EK1501', '2.x', ['sdk_path' => '/opt/soem/bin/ecat_master'], 'EtherCAT Coupler, SOEM compatible'),
            ],
        ));

        // ── Siemens ───────────────────────────────
        $factory->register(new VendorProfile(
            name:       'siemens',
            protocol:   'profinet',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 34964,
            homepage:   'https://www.siemens.com/simatic',
            description: 'Siemens SIMATIC — PROFINET via Open Communication',
            devices: [
                new DeviceProfile('S7-1200', 'V4.x', ['port' => 34964], 'Compact controller, PROFINET onboard'),
                new DeviceProfile('S7-1500', 'V3.x', ['port' => 34964], 'Advanced controller, integrated PROFINET'),
                new DeviceProfile('ET 200SP', 'V2.x', ['port' => 34964], 'Distributed I/O, PROFINET interface'),
                new DeviceProfile('ET 200MP', 'V2.x', ['port' => 34964], 'Multi-purpose distributed I/O'),
                new DeviceProfile('S7-400', 'V6.x', ['port' => 34964], 'High-end process controller'),
            ],
        ));

        // ── B&R ───────────────────────────────────
        $factory->register(new VendorProfile(
            name:       'br-automation',
            protocol:   'powerlink',
            bridgeType: 'external-process',
            sdkPath:    '/opt/br/automation-studio/bin/powerlink_master',
            defaultPort: 0,
            homepage:   'https://www.br-automation.com',
            description: 'B&R Automation — POWERLINK Managing/Controlled Node',
            devices: [
                new DeviceProfile('X20CP1584', 'AR 4.x', [], 'POWERLINK controller'),
                new DeviceProfile('X20CP1586', 'AR 4.x', [], 'High-performance controller'),
                new DeviceProfile('ACOPOS P3', 'AR 4.x', [], 'POWERLINK servo drive'),
                new DeviceProfile('X20BC0083', 'AR 3.x', ['sdk_path' => '/opt/openPOWERLINK/bin/oplk_master'], 'POWERLINK bus controller, openPOWERLINK'),
            ],
        ));

        // ── Bosch Rexroth ─────────────────────────
        $factory->register(new VendorProfile(
            name:       'bosch-rexroth',
            protocol:   'sercos',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 5500,
            homepage:   'https://www.boschrexroth.com',
            description: 'Bosch Rexroth — SERCOS III via Hilscher netX / ctrlX CORE',
            devices: [
                new DeviceProfile('IndraDrive Cs', 'MPx-20', ['port' => 5500], 'SERCOS III servo drive'),
                new DeviceProfile('IndraDrive Mi', 'MPx-20', ['port' => 5500], 'SERCOS III modular drive'),
                new DeviceProfile('ctrlX CORE', 'V1.x', ['port' => 8443], 'ctrlX OS with SERCOS III'),
                new DeviceProfile('HMV01', '3.x', ['port' => 5500], 'SERCOS III supply module'),
            ],
        ));

        // ── Hilscher ──────────────────────────────
        $factory->register(new VendorProfile(
            name:       'hilscher',
            protocol:   'multi',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 5000,
            homepage:   'https://www.hilscher.com',
            description: 'Hilscher netX — multi-protocol gateway (EtherCAT/PROFINET/POWERLINK/SERCOS III/EtherNet/IP)',
            devices: [
                new DeviceProfile('netX 90', 'V1.x', ['port' => 5000], 'Dual-port SoC, any protocol'),
                new DeviceProfile('netX 4000', 'V3.x', ['port' => 5000], 'Multi-channel master, any protocol'),
                new DeviceProfile('cifX RE', 'PCI', ['port' => 5000, 'transport' => 'tcp'], 'PC card, Real-Time Ethernet'),
                new DeviceProfile('comX', 'V1.x', ['port' => 5000], 'Communication module'),
                new DeviceProfile('cifX DP', 'V2.x', ['port' => 5000], 'PROFIBUS DP Master'),
                new DeviceProfile('cifX CAN', 'V1.x', ['port' => 5000], 'CANopen Master'),
            ],
        ));

        // ── HMS / Anybus ──────────────────────────
        $factory->register(new VendorProfile(
            name:       'hms-anybus',
            protocol:   'multi',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 502,
            homepage:   'https://www.anybus.com',
            description: 'HMS Anybus — multi-protocol gateway (EtherNet/IP to Modbus/PROFIBUS/PROFINET)',
            devices: [
                new DeviceProfile('Anybus Communicator', 'Firmware 2.x', ['port' => 502], 'Protocol converter, serial/fieldbus'),
                new DeviceProfile('Anybus X-gateway', 'Firmware 4.x', ['port' => 502], 'Dual-network bridge'),
                new DeviceProfile('Anybus CompactCom', 'B40', ['port' => 502], 'Embedded communication module'),
                new DeviceProfile('Anybus Wireless Bolt', 'V1.x', ['port' => 502, 'transport' => 'tcp'], 'Wireless IoT gateway'),
                new DeviceProfile('Anybus PROFIBUS Slave', 'V2.x', ['port' => 502], 'PROFIBUS DP Slave interface'),
                new DeviceProfile('Anybus DeviceNet Scanner', 'V2.x', ['port' => 502], 'DeviceNet Master/Scanner'),
                new DeviceProfile('Anybus CANopen Slave', 'V1.x', ['port' => 502], 'CANopen Slave interface'),
                new DeviceProfile('Anybus CC-Link Slave', 'V1.x', ['port' => 502], 'CC-Link Slave interface'),
            ],
        ));

        // ── Moxa ──────────────────────────────────
        $factory->register(new VendorProfile(
            name:       'moxa',
            protocol:   'multi',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 502,
            homepage:   'https://www.moxa.com',
            description: 'Moxa — Industrial Ethernet gateways (Modbus/Profinet/EtherNet/IP)',
            devices: [
                new DeviceProfile('MGate 5101-PBM-MN', 'V3.x', ['port' => 502], 'Modbus RTU/TCP to PROFINET'),
                new DeviceProfile('MGate 5102-PBM-PN', 'V3.x', ['port' => 502], 'PROFIBUS to PROFINET'),
                new DeviceProfile('MGate 5105-MB-EIP', 'V3.x', ['port' => 502], 'Modbus to EtherNet/IP'),
                new DeviceProfile('MGate 5118', 'V2.x', ['port' => 502], 'EtherNet/IP to PROFINET'),
                new DeviceProfile('MGate 4101-MB-PBS', 'V3.x', ['port' => 502], 'Modbus to PROFIBUS'),
            ],
        ));

        // ── Phoenix Contact ───────────────────────
        $factory->register(new VendorProfile(
            name:       'phoenix-contact',
            protocol:   'profinet',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 34964,
            homepage:   'https://www.phoenixcontact.com',
            description: 'Phoenix Contact — PROFINET/EtherNet/IP I/O systems',
            devices: [
                new DeviceProfile('AXL F BK PN', 'V2.x', ['port' => 34964], 'PROFINET bus coupler'),
                new DeviceProfile('AXL F IL ETH', 'V2.x', ['port' => 44818], 'EtherNet/IP bus coupler'),
                new DeviceProfile('AXL E ETH DI16', 'V2.x', ['port' => 44818], 'EtherNet/IP digital input'),
                new DeviceProfile('ILC 191', 'V1.x', ['port' => 34964], 'Inline controller, PROFINET'),
            ],
        ));

        // ── Bihl+Wiedemann ─────────────────────────
        $factory->register(new VendorProfile(
            name:       'bihl-wiedemann',
            protocol:   'as-interface',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 502,
            homepage:   'https://www.bihl-wiedemann.de',
            description: 'Bihl+Wiedemann AS-Interface Safety at Work',
            devices: [
                new DeviceProfile('BWU3540', 'V3.x', [], 'AS-i Master, PROFINET'),
                new DeviceProfile('BWU3675', 'V4.x', [], 'AS-i Master, EtherNet/IP'),
            ],
        ));

        // ── ifm electronic ─────────────────────────
        $factory->register(new VendorProfile(
            name:       'ifm',
            protocol:   'io-link',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 502,
            homepage:   'https://www.ifm.com',
            description: 'ifm IO-Link Master',
            devices: [
                new DeviceProfile('AL1330', 'V1.x', [], 'IO-Link Master, PROFINET'),
                new DeviceProfile('AL1340', 'V1.x', [], 'IO-Link Master, EtherNet/IP'),
            ],
        ));

        // ── Pepperl+Fuchs ──────────────────────────
        $factory->register(new VendorProfile(
            name:       'pepperl-fuchs',
            protocol:   'multi',
            bridgeType: 'tcp-gateway',
            sdkPath:    '',
            defaultPort: 502,
            homepage:   'https://www.pepperl-fuchs.com',
            description: 'Pepperl+Fuchs AS-i Gateway / HART multiplexer',
            devices: [
                new DeviceProfile('VBA-4E-G20', 'V2.x', [], 'AS-i Gateway'),
                new DeviceProfile('KFD2-HMM-16', 'V1.x', [], 'HART Multiplexer'),
            ],
        ));

        // ── Softing ────────────────────────────────
        $factory->register(new VendorProfile(
            name:       'softing',
            protocol:   'multi',
            bridgeType: 'external-process',
            sdkPath:    '/opt/softing/bin/',
            defaultPort: 0,
            homepage:   'https://www.softing.com',
            description: 'Softing PROFIBUS/FF/HART interface cards',
            devices: [
                new DeviceProfile('FG-200', 'V2.x', [], 'FF H1 USB Interface'),
                new DeviceProfile('PROFIusb', 'V3.x', [], 'PROFIBUS USB Interface'),
            ],
        ));
    }
}
