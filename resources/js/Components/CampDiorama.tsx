import { Canvas, useFrame } from '@react-three/fiber';
import { Float, ContactShadows } from '@react-three/drei';
import { Suspense, useEffect, useRef, useState } from 'react';
import * as THREE from 'three';

type Vec3 = [number, number, number];

const reduce =
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Cùng một khu trại, đổi khung cảnh xung quanh: cỏ → rừng → núi → biển
const WORLDS = [
    { key: 'grass', name: 'Đồng cỏ', ground: '#6e9440' },
    { key: 'forest', name: 'Rừng thông', ground: '#5b7d33' },
    { key: 'mountain', name: 'Núi', ground: '#86a468' },
    { key: 'sea', name: 'Biển', ground: '#d8c69a' },
];

const smooth = (t: number) => t * t * (3 - 2 * t);

function Pine({ position, scale = 1 }: { position: Vec3; scale?: number }) {
    return (
        <group position={position} scale={scale}>
            <mesh position={[0, 0.32, 0]}>
                <cylinderGeometry args={[0.06, 0.09, 0.34, 6]} />
                <meshStandardMaterial color="#5b4326" flatShading transparent />
            </mesh>
            <mesh position={[0, 0.85, 0]}>
                <coneGeometry args={[0.4, 0.9, 7]} />
                <meshStandardMaterial color="#3f6322" flatShading transparent />
            </mesh>
            <mesh position={[0, 1.25, 0]}>
                <coneGeometry args={[0.28, 0.66, 7]} />
                <meshStandardMaterial color="#557a2b" flatShading transparent />
            </mesh>
        </group>
    );
}

function Peak({ position, h }: { position: Vec3; h: number }) {
    return (
        <group position={position}>
            <mesh>
                <coneGeometry args={[h * 0.7, h, 5]} />
                <meshStandardMaterial color="#8294a4" flatShading transparent />
            </mesh>
            <mesh position={[0, h * 0.34, 0]}>
                <coneGeometry args={[h * 0.28, h * 0.32, 5]} />
                <meshStandardMaterial color="#f4f8fb" flatShading transparent />
            </mesh>
        </group>
    );
}

// --- môi trường theo từng nơi (mỗi nhóm tự fade in/out) ---
function EnvGrass() {
    const tufts: Vec3[] = [
        [-1.6, 0.05, -0.4], [-1.1, 0.05, 1.2], [1.5, 0.05, 1.1], [1.9, 0.05, -0.6],
        [-0.6, 0.05, 1.8], [0.9, 0.05, -1.8], [-1.9, 0.05, 0.7], [1.2, 0.05, 1.9],
    ];
    return (
        <group>
            {tufts.map((p, i) => (
                <mesh key={i} position={p} scale={0.5 + (i % 3) * 0.15}>
                    <coneGeometry args={[0.16, 0.45, 5]} />
                    <meshStandardMaterial color="#5e8a2e" flatShading transparent />
                </mesh>
            ))}
        </group>
    );
}
function EnvForest() {
    return (
        <group>
            <Pine position={[-1.7, 0, -0.5]} scale={1.15} />
            <Pine position={[-1.0, 0, 1.3]} scale={0.85} />
            <Pine position={[1.6, 0, -1.2]} scale={1} />
            <Pine position={[1.8, 0, 0.7]} scale={0.9} />
            <Pine position={[0.4, 0, 1.9]} scale={0.8} />
        </group>
    );
}
function EnvMountain() {
    return (
        <group>
            <Peak position={[-1.5, 0.4, -1.4]} h={1.7} />
            <Peak position={[0.2, 0.5, -1.9]} h={2.1} />
            <Peak position={[1.7, 0.35, -1.3]} h={1.5} />
            <mesh position={[-1.4, 0.05, 1.2]}>
                <dodecahedronGeometry args={[0.26, 0]} />
                <meshStandardMaterial color="#8a8f82" flatShading transparent />
            </mesh>
        </group>
    );
}
function EnvSea() {
    return (
        <group>
            {/* mặt nước */}
            <mesh position={[1.5, 0.02, 0.9]} rotation={[-Math.PI / 2, 0, 0]}>
                <circleGeometry args={[1.7, 32]} />
                <meshStandardMaterial color="#4e90ac" transparent opacity={0.9} />
            </mesh>
            {/* cây dừa */}
            <group position={[-1.5, 0, -0.4]} rotation={[0, 0, 0.18]}>
                <mesh position={[0, 0.7, 0]}>
                    <cylinderGeometry args={[0.07, 0.1, 1.4, 6]} />
                    <meshStandardMaterial color="#7a5a32" flatShading transparent />
                </mesh>
                {[0, 1, 2, 3, 4].map((n) => (
                    <mesh key={n} position={[0, 1.4, 0]} rotation={[0.5, (n / 5) * Math.PI * 2, 0]}>
                        <coneGeometry args={[0.12, 0.9, 4]} />
                        <meshStandardMaterial color="#3f7a32" flatShading transparent />
                    </mesh>
                ))}
            </group>
        </group>
    );
}

function Tent() {
    return (
        <group position={[0, 0.12, 0]}>
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
        if (light.current) light.current.intensity = 2 + Math.sin(clock.elapsedTime * 9) * 0.5;
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

function setOpacity(g: THREE.Group | null, op: number) {
    if (!g) return;
    g.visible = op > 0.02;
    g.scale.setScalar(0.86 + 0.14 * op);
    g.traverse((o) => {
        const m = (o as THREE.Mesh).material as THREE.Material | undefined;
        if (m) {
            m.transparent = true;
            m.opacity = op;
        }
    });
}

function Scene({ index }: { index: number }) {
    const groundMat = useRef<THREE.MeshStandardMaterial>(null);
    const envRefs = useRef<(THREE.Group | null)[]>([]);
    const from = useRef(index);
    const to = useRef(index);
    const t = useRef(1);
    const colFrom = useRef(new THREE.Color(WORLDS[index].ground));
    const colTo = useRef(new THREE.Color(WORLDS[index].ground));
    const scratch = useRef(new THREE.Color());

    useEffect(() => {
        from.current = to.current;
        to.current = index;
        colFrom.current.set(WORLDS[from.current].ground);
        colTo.current.set(WORLDS[index].ground);
        t.current = 0;
    }, [index]);

    useFrame((_, delta) => {
        if (t.current < 1) t.current = Math.min(1, t.current + delta / 1.1);
        const e = smooth(t.current);
        // ground morph màu
        if (groundMat.current) {
            scratch.current.copy(colFrom.current).lerp(colTo.current, e);
            groundMat.current.color.copy(scratch.current);
        }
        // env fade in/out
        for (let i = 0; i < WORLDS.length; i++) {
            const op = i === to.current ? e : i === from.current ? 1 - e : 0;
            setOpacity(envRefs.current[i], op);
        }
    });

    return (
        <Float speed={1.1} rotationIntensity={0.06} floatIntensity={0.4}>
            <group>
                <mesh receiveShadow position={[0, -0.35, 0]}>
                    <cylinderGeometry args={[3, 3.1, 0.6, 56]} />
                    <meshStandardMaterial ref={groundMat} color={WORLDS[index].ground} flatShading />
                </mesh>
                <mesh position={[0, -0.82, 0]}>
                    <cylinderGeometry args={[3.05, 2.3, 0.55, 56]} />
                    <meshStandardMaterial color="#5b4326" flatShading />
                </mesh>

                <group ref={(el) => (envRefs.current[0] = el)}><EnvGrass /></group>
                <group ref={(el) => (envRefs.current[1] = el)}><EnvForest /></group>
                <group ref={(el) => (envRefs.current[2] = el)}><EnvMountain /></group>
                <group ref={(el) => (envRefs.current[3] = el)}><EnvSea /></group>

                <Tent />
                <Campfire />
            </group>
        </Float>
    );
}

export default function CampDiorama() {
    const [i, setI] = useState(0);
    useEffect(() => {
        if (reduce) return;
        const id = setInterval(() => setI((v) => (v + 1) % WORLDS.length), 4200);
        return () => clearInterval(id);
    }, []);

    return (
        <div className="relative h-full w-full">
            <Canvas shadows dpr={[1, 1.8]} camera={{ position: [0, 2.7, 6.4], fov: 42 }} style={{ width: '100%', height: '100%' }}>
                <Suspense fallback={null}>
                    <hemisphereLight args={['#cfe3c4', '#5b4326', 0.75]} />
                    <directionalLight position={[4, 7, 3]} intensity={1.6} castShadow shadow-mapSize-width={1024} shadow-mapSize-height={1024} />
                    <fog attach="fog" args={['#e7ecdb', 10, 18]} />
                    <Scene index={i} />
                    <ContactShadows position={[0, -0.66, 0]} opacity={0.32} scale={9} blur={2.6} far={4} />
                </Suspense>
            </Canvas>

            {/* nhãn nơi chốn */}
            <div className="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center gap-2">
                {WORLDS.map((w, idx) => (
                    <span
                        key={w.key}
                        className={`rounded-full px-3 py-1 font-mono text-[0.66rem] uppercase tracking-wider transition-all duration-500 ${
                            idx === i ? 'scale-105 bg-grass text-ground shadow' : 'bg-surface/80 text-moss'
                        }`}
                    >
                        {w.name}
                    </span>
                ))}
            </div>
        </div>
    );
}
