import { Canvas, useFrame } from '@react-three/fiber';
import { Float, ContactShadows } from '@react-three/drei';
import { Suspense, useRef } from 'react';
import * as THREE from 'three';

type Vec3 = [number, number, number];

const reduce =
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function Tree({ position, scale = 1 }: { position: Vec3; scale?: number }) {
    return (
        <group position={position} scale={scale}>
            <mesh position={[0, 0.35, 0]} castShadow>
                <cylinderGeometry args={[0.06, 0.09, 0.35, 6]} />
                <meshStandardMaterial color="#5b4326" flatShading />
            </mesh>
            <mesh position={[0, 0.9, 0]} castShadow>
                <coneGeometry args={[0.42, 0.95, 7]} />
                <meshStandardMaterial color="#3f6322" flatShading />
            </mesh>
            <mesh position={[0, 1.32, 0]} castShadow>
                <coneGeometry args={[0.3, 0.7, 7]} />
                <meshStandardMaterial color="#557a2b" flatShading />
            </mesh>
        </group>
    );
}

function Tent({ position }: { position: Vec3 }) {
    return (
        <group position={position}>
            <mesh rotation={[0, Math.PI / 4, 0]} castShadow>
                <coneGeometry args={[0.95, 1.1, 4]} />
                <meshStandardMaterial color="#557a2b" flatShading />
            </mesh>
            <mesh position={[0, -0.18, 0.62]}>
                <planeGeometry args={[0.34, 0.62]} />
                <meshStandardMaterial color="#22341a" side={THREE.DoubleSide} />
            </mesh>
        </group>
    );
}

function Campfire() {
    const light = useRef<THREE.PointLight>(null);
    useFrame(({ clock }) => {
        if (light.current) {
            light.current.intensity =
                2 + Math.sin(clock.elapsedTime * 9) * 0.5 + Math.sin(clock.elapsedTime * 23) * 0.2;
        }
    });
    return (
        <group position={[1.25, -0.05, 0.7]}>
            <pointLight ref={light} color="#ff8a3c" intensity={2} distance={5} position={[0, 0.4, 0]} />
            <mesh>
                <coneGeometry args={[0.14, 0.34, 8]} />
                <meshStandardMaterial color="#c97b36" emissive="#ff7a2c" emissiveIntensity={1.4} />
            </mesh>
        </group>
    );
}

function Diorama() {
    const g = useRef<THREE.Group>(null);
    useFrame((_, delta) => {
        if (g.current) g.current.rotation.y += delta * (reduce ? 0.04 : 0.18);
    });
    return (
        <group ref={g}>
            <mesh receiveShadow position={[0, -0.35, 0]}>
                <cylinderGeometry args={[3, 3.1, 0.6, 56]} />
                <meshStandardMaterial color="#6e9440" flatShading />
            </mesh>
            <mesh position={[0, -0.82, 0]}>
                <cylinderGeometry args={[3.05, 2.3, 0.55, 56]} />
                <meshStandardMaterial color="#5b4326" flatShading />
            </mesh>
            <Tent position={[0, 0.12, 0]} />
            <Tree position={[-1.7, 0, -0.6]} scale={1.1} />
            <Tree position={[-1.05, 0, 1.25]} scale={0.8} />
            <Tree position={[1.6, 0, -1.25]} scale={0.95} />
            <mesh position={[-0.35, 0, 1.55]} castShadow>
                <dodecahedronGeometry args={[0.2, 0]} />
                <meshStandardMaterial color="#8a8f82" flatShading />
            </mesh>
            <mesh position={[0.5, 0, 1.7]} castShadow>
                <dodecahedronGeometry args={[0.12, 0]} />
                <meshStandardMaterial color="#9aa091" flatShading />
            </mesh>
            <Campfire />
        </group>
    );
}

export default function CampDiorama() {
    return (
        <Canvas shadows dpr={[1, 1.8]} camera={{ position: [0, 2.6, 6.2], fov: 42 }} style={{ width: '100%', height: '100%' }}>
            <Suspense fallback={null}>
                <hemisphereLight args={['#cfe3c4', '#5b4326', 0.75]} />
                <directionalLight
                    position={[4, 7, 3]}
                    intensity={1.6}
                    castShadow
                    shadow-mapSize-width={1024}
                    shadow-mapSize-height={1024}
                />
                <fog attach="fog" args={['#e7ecdb', 9, 17]} />
                <Float speed={1.1} rotationIntensity={0.12} floatIntensity={0.5}>
                    <Diorama />
                </Float>
                <ContactShadows position={[0, -0.66, 0]} opacity={0.35} scale={9} blur={2.6} far={4} />
            </Suspense>
        </Canvas>
    );
}
